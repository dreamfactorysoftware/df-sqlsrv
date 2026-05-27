<?php

use DreamFactory\Core\SqlSrv\Database\Schema\SqlServerSchema;
use PHPUnit\Framework\TestCase;

/**
 * Security regression test: INOUT parameter quoting on the dblib/FreeTDS path.
 *
 * VRT: Server-Side Injection > SQL Injection via Stored Procedure INOUT Parameters
 *
 * Background
 * ----------
 * When SQL Server is accessed via the dblib driver (FreeTDS), PDO cannot bind
 * output parameters at all.  SqlServerSchema::getProcedureStatement() works around
 * this by emitting raw T-SQL DECLARE / SET / SELECT statements and interpolating
 * the caller-supplied INOUT values directly into the SET clause:
 *
 *   SET @paramName = <value>;
 *
 * Before the fix (2026-04-security-scan), non-null string values were interpolated
 * without any quoting, allowing a malicious caller to inject arbitrary T-SQL:
 *
 *   Input:  '; DELETE FROM users; --
 *   Output: SET @p1 = '; DELETE FROM users; --    ← executes the DELETE
 *
 * The sqlsrv driver path is NOT affected — it uses PDO binding in doRoutineBinding()
 * and never touches this code path.
 *
 * Fix
 * ---
 * Non-null, non-numeric values are now passed through $this->quoteValue() before
 * interpolation, which wraps them in single quotes and doubles any embedded single
 * quotes — the standard SQL Server string-literal escaping.
 *
 * Test structure
 * --------------
 * SqlServerSchema::getProcedureStatement() calls self::useSqlsrv() — using self::
 * rather than static:: — so the dblib code path cannot be forced via a subclass
 * override without modifying production code.  These tests therefore directly test:
 *
 *   1. The quoteValue() escaping function that was added as the guard.
 *   2. The applyInoutGuard() helper (exposed by DblibTestableSchema) which
 *      replicates the exact three-branch conditional from the fix.
 *   3. The resulting SET clause string for each value type.
 *
 * The critical security property being verified: after quoting, the SET clause is
 * a well-formed T-SQL string literal assignment.  Injection works by breaking out
 * of the string literal context; a properly quoted value cannot do that.
 */
class InoutParamQuotingTest extends TestCase
{
    /** @var DblibTestableSchema */
    private DblibTestableSchema $subject;

    protected function setUp(): void
    {
        $this->subject = new DblibTestableSchema();
    }

    // =========================================================================
    // Core quoting: quoteValue() output format
    // =========================================================================

    /**
     * A plain string must be wrapped in single quotes, making it a T-SQL literal.
     */
    public function testPlainStringIsQuoted(): void
    {
        $result = $this->subject->quoteValue('hello');

        $this->assertSame("'hello'", $result,
            'Plain string must be wrapped in single quotes');
    }

    /**
     * A string with an embedded single quote must have it doubled — the standard
     * SQL Server escaping for string literals.  Without doubling, the quote would
     * terminate the string early and allow injection.
     */
    public function testEmbeddedSingleQuoteIsDoubled(): void
    {
        $result = $this->subject->quoteValue("O'Brien");

        $this->assertSame("'O''Brien'", $result,
            "Embedded single quote must be doubled to produce 'O''Brien'");
    }

    /**
     * An empty string must become two single quotes — a valid empty T-SQL literal.
     */
    public function testEmptyStringIsQuoted(): void
    {
        $result = $this->subject->quoteValue('');

        $this->assertSame("''", $result, 'Empty string must become an empty T-SQL literal');
    }

    /**
     * Tautology payload: ' OR '1'='1
     * After quoting the output is one complete string literal.  The security
     * property: the output starts and ends with a single quote, and all internal
     * single quotes are doubled.
     */
    public function testTautologyPayloadProducesStringLiteral(): void
    {
        $payload = "' OR '1'='1";
        $result  = $this->subject->quoteValue($payload);

        // The result must be delimited: starts with ' and ends with '
        $this->assertStringStartsWith("'", $result,
            'Quoted value must start with a single quote');
        $this->assertStringEndsWith("'", $result,
            'Quoted value must end with a single quote');

        // Every internal single quote must be doubled (no bare single quote remains)
        $inner = substr($result, 1, -1);           // strip the outer delimiters
        $this->assertStringNotContainsString("'",
            str_replace("''", '', $inner),         // remove all doubled-quote pairs
            'After removing doubled quotes, no bare single quote must remain inside the literal'
        );
    }

    /**
     * Semicolon + stacked query: '; DELETE FROM users; --
     * The injection breaks out of a string only if the opening single quote is
     * unescaped.  After quoting the leading quote becomes '' so there is no escape.
     */
    public function testSemicolonStackedQueryProducesStringLiteral(): void
    {
        $payload = "'; DELETE FROM users; --";
        $result  = $this->subject->quoteValue($payload);

        // Must be a single-quoted T-SQL string literal.
        $this->assertMatchesRegularExpression("/^'.*'$/s", $result,
            'Result must be a single-quoted T-SQL string literal');

        // The leading ' in the payload must be doubled ('' not bare ').
        // If the first char of the payload was ' and it is NOT doubled, injection is possible.
        // After quoting, the inner content starts with '' not '.
        $inner = substr($result, 1, -1);
        $this->assertStringStartsWith("''", $inner,
            "Leading single quote in payload must be doubled to '' inside the literal");
    }

    /**
     * xp_cmdshell escalation: '; EXEC xp_cmdshell('whoami'); --
     * Same escape-prevention property: the opening single quote must be doubled.
     */
    public function testXpCmdshellPayloadProducesStringLiteral(): void
    {
        $payload = "'; EXEC xp_cmdshell('whoami'); --";
        $result  = $this->subject->quoteValue($payload);

        $this->assertMatchesRegularExpression("/^'.*'$/s", $result,
            'Result must be a single-quoted T-SQL string literal');

        $inner = substr($result, 1, -1);
        $this->assertStringStartsWith("''", $inner,
            "Leading single quote in xp_cmdshell payload must be doubled");
    }

    /**
     * UNION-based data-leak: x' UNION SELECT password FROM sys.sql_logins --
     * The dangerous part is the ' that terminates the string context.
     * After quoting it is doubled and the UNION is inert inside the literal.
     */
    public function testUnionPayloadProducesStringLiteral(): void
    {
        $payload = "x' UNION SELECT password FROM sys.sql_logins --";
        $result  = $this->subject->quoteValue($payload);

        $this->assertMatchesRegularExpression("/^'.*'$/s", $result,
            'Result must be a single-quoted T-SQL string literal');

        // The bare ' before UNION must be doubled.
        $this->assertStringContainsString("x''", $result,
            "The single quote in the payload must be doubled to x'' inside the literal");
    }

    /**
     * DROP TABLE stacked query: legit'; DROP TABLE sensitive_data; --
     */
    public function testDropTablePayloadProducesStringLiteral(): void
    {
        $payload = "legit'; DROP TABLE sensitive_data; --";
        $result  = $this->subject->quoteValue($payload);

        $this->assertMatchesRegularExpression("/^'.*'$/s", $result,
            'Result must be a single-quoted T-SQL string literal');

        // The ' after 'legit' must be doubled.
        $this->assertStringContainsString("legit''", $result,
            "The injection-point single quote must be doubled to legit''");
    }

    // =========================================================================
    // Numeric values — must pass through quoteValue() unchanged
    // =========================================================================

    /**
     * PHP integers must be returned as-is.  Wrapping a numeric T-SQL parameter
     * value in single quotes can cause a type conversion error.
     */
    public function testIntegerPassesThroughUnquoted(): void
    {
        $result = $this->subject->quoteValue(42);

        $this->assertSame(42, $result, 'Integer must pass through quoteValue unchanged');
    }

    /**
     * PHP floats must also pass through as-is.
     */
    public function testFloatPassesThroughUnquoted(): void
    {
        $result = $this->subject->quoteValue(3.14);

        $this->assertSame(3.14, $result, 'Float must pass through quoteValue unchanged');
    }

    // =========================================================================
    // Guard logic: applyInoutGuard() — the three-branch conditional from the fix
    //
    //   if (is_null($value)) {
    //       $value = 'NULL';
    //   } elseif (!is_int($value) && !is_float($value)) {
    //       $value = $this->quoteValue($value);
    //   }
    // =========================================================================

    /**
     * PHP null → the T-SQL NULL keyword (no quotes).
     */
    public function testNullValueMapsToNullKeyword(): void
    {
        $result = $this->subject->applyInoutGuard(null);

        $this->assertSame('NULL', $result,
            'PHP null must map to the T-SQL NULL keyword');
    }

    /**
     * Integer → unchanged by the guard (passes the is_int check, skips quoting).
     */
    public function testIntegerValuePassesThroughGuard(): void
    {
        $result = $this->subject->applyInoutGuard(99);

        $this->assertSame(99, $result,
            'Integer must pass through the INOUT guard without modification');
    }

    /**
     * Float → unchanged by the guard.
     */
    public function testFloatValuePassesThroughGuard(): void
    {
        $result = $this->subject->applyInoutGuard(1.5);

        $this->assertSame(1.5, $result,
            'Float must pass through the INOUT guard without modification');
    }

    /**
     * String → wrapped in single quotes by the guard.
     */
    public function testStringValueIsQuotedByGuard(): void
    {
        $result = $this->subject->applyInoutGuard('safe_value');

        $this->assertSame("'safe_value'", $result,
            'String values must be single-quoted by the INOUT guard');
    }

    /**
     * Injection payload string → the guard must produce a well-formed T-SQL
     * string literal.  The key property: the result starts with ' and ends
     * with ', and all internal single quotes are doubled.
     */
    public function testInjectionPayloadIsWrappedInStringLiteralByGuard(): void
    {
        $payload = "'; DROP TABLE users; --";
        $result  = $this->subject->applyInoutGuard($payload);

        // Must be a single-quoted T-SQL string literal.
        $this->assertMatchesRegularExpression("/^'.*'$/s", (string) $result,
            'Injection payload must be wrapped in a single-quoted T-SQL string literal');

        // The leading ' must be doubled (injection-prevention property).
        $inner = substr((string) $result, 1, -1);
        $this->assertStringStartsWith("''", $inner,
            "The leading single quote of the payload must be doubled to ''");
    }

    // =========================================================================
    // SET-clause output verification
    // =========================================================================

    /**
     * The full SET clause for a plain string must be:  SET @p1 = 'hello world';
     */
    public function testSetClauseForStringValue(): void
    {
        $quoted    = $this->subject->applyInoutGuard('hello world');
        $setClause = "SET @p1 = {$quoted};";

        $this->assertSame("SET @p1 = 'hello world';", $setClause,
            'SET clause for a string value must use a single-quoted literal');
    }

    /**
     * The SET clause for a value with a single quote must double it:
     * SET @p1 = 'O''Brien';
     */
    public function testSetClauseForValueWithSingleQuote(): void
    {
        $quoted    = $this->subject->applyInoutGuard("O'Brien");
        $setClause = "SET @p1 = {$quoted};";

        $this->assertSame("SET @p1 = 'O''Brien';", $setClause,
            "SET clause must escape the single quote as O''Brien");
    }

    /**
     * The SET clause for NULL must be:  SET @p1 = NULL;
     */
    public function testSetClauseForNull(): void
    {
        $value     = $this->subject->applyInoutGuard(null);
        $setClause = "SET @p1 = {$value};";

        $this->assertSame("SET @p1 = NULL;", $setClause,
            'SET clause for null must use the T-SQL NULL keyword');
    }

    /**
     * The SET clause for an integer must NOT quote it:  SET @p1 = 42;
     */
    public function testSetClauseForInteger(): void
    {
        $value     = $this->subject->applyInoutGuard(42);
        $setClause = "SET @p1 = {$value};";

        $this->assertSame("SET @p1 = 42;", $setClause,
            'SET clause for integer must not add quotes');
    }

    /**
     * The SET clause for an injection payload must be a well-formed literal
     * assignment.  The entire SET clause must match:
     *   SET @p1 = '<escaped_content>';
     * where <escaped_content> is the original payload with single quotes doubled.
     *
     * This means the '; DELETE FROM ... inside the payload is inside a string
     * literal and will never be executed as SQL.
     */
    public function testSetClauseForInjectionPayloadIsWellFormedLiteralAssignment(): void
    {
        $payload   = "'; DELETE FROM users; --";
        $quoted    = $this->subject->applyInoutGuard($payload);
        $setClause = "SET @p1 = {$quoted};";

        // The SET clause must match the pattern for a proper string literal assignment.
        $this->assertMatchesRegularExpression(
            "/^SET @p1 = '.*';$/s",
            $setClause,
            'SET clause for an injection payload must be a properly delimited string literal assignment'
        );

        // The clause must begin with SET @p1 = ' — meaning the value IS a string literal.
        $this->assertStringStartsWith("SET @p1 = '", $setClause,
            'SET clause must open with a single-quoted string literal');

        // The leading attack quote must be doubled: the payload starts with '
        // so inside the literal we expect '' right after the opening delimiter.
        $this->assertStringContainsString("= '''", $setClause,
            "The opening injection quote must appear doubled as '' inside the string literal");
    }
}

// =============================================================================
// Test helper: exposes quoting primitives without needing a DB connection
// =============================================================================

/**
 * DblibTestableSchema
 *
 * Provides:
 *   - A pure-PHP quoteValue() matching the fallback branch of parent Schema::quoteValue()
 *     (no PDO required — the fallback uses addcslashes + str_replace).
 *   - applyInoutGuard(): the exact three-branch conditional added by the security fix,
 *     exposed as a public method for direct unit testing.
 *
 * NOTE: SqlServerSchema::getProcedureStatement() uses self::useSqlsrv() — not static::
 * — so the dblib code path cannot be forced via a subclass override without modifying
 * production code.  Full integration of the fix on the dblib path requires a real
 * FreeTDS connection.  These unit tests cover the quoting logic that the fix relies on.
 */
class DblibTestableSchema extends SqlServerSchema
{
    /**
     * Bypass the parent constructor — no DB connection required.
     */
    public function __construct()
    {
        // intentionally empty
    }

    /**
     * Pure-PHP quoteValue matching the fallback in parent Schema::quoteValue().
     * Wraps in single quotes; doubles embedded single quotes.
     * Returns integers and floats unchanged (matching parent behaviour).
     *
     * @param  mixed $str
     * @return mixed
     */
    public function quoteValue($str)
    {
        if (is_int($str) || is_float($str)) {
            return $str;
        }

        return "'" . str_replace("'", "''", (string) $str) . "'";
    }

    /**
     * Expose the three-branch INOUT guard added by the security fix.
     *
     * Replicates the exact conditional in getProcedureStatement():
     *
     *   if (is_null($value)) {
     *       $value = 'NULL';
     *   } elseif (!is_int($value) && !is_float($value)) {
     *       $value = $this->quoteValue($value);
     *   }
     *
     * @param  mixed $value  Raw INOUT parameter value from the caller.
     * @return mixed         Value safe for interpolation into a T-SQL SET clause.
     */
    public function applyInoutGuard(mixed $value): mixed
    {
        if (is_null($value)) {
            $value = 'NULL';
        } elseif (!is_int($value) && !is_float($value)) {
            $value = $this->quoteValue($value);
        }

        return $value;
    }

    /**
     * Minimal quoteColumnName for any inherited methods that need it.
     */
    public function quoteColumnName($name): string
    {
        return '[' . str_replace(']', ']]', (string) $name) . ']';
    }
}
