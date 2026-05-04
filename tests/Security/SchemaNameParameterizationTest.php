<?php

namespace DreamFactory\Core\SqlSrv\Tests\Security;

use PHPUnit\Framework\TestCase;

/**
 * Security: SqlServerSchema methods that take a schema or routine name must
 * use parameterized bindings, not string interpolation, when building SQL.
 *
 * The April 2026 audit (df-sqlsrv F-03..F-07) found 4 interpolation sites
 * across information-schema lookup methods:
 *
 *   - getTableConstraints():     IN ('{$schema}')
 *   - getTableNames():           TABLE_SCHEMA = '$schema'
 *   - getViewNames():            TABLE_SCHEMA = '$schema'
 *   - loadParameters():          '{$holder->resourceName}' / '{$holder->schemaName}'
 *
 * Schema names typically arrive from URL/route parameters and routine names
 * from caller-supplied schema queries. Even though most callers feed values
 * derived from prior selects, the pattern is unsafe: any future code path
 * that lets attacker influence routine_name or schema becomes SQLi.
 *
 * After the fix, each of these methods uses `:schema` / `:name` bindings
 * passed as the second argument to `connection->select()` — same pattern
 * `getRoutineNames()` already uses correctly.
 */
class SchemaNameParameterizationTest extends TestCase
{
    private string $sourcePath;
    private string $contents;

    protected function setUp(): void
    {
        $this->sourcePath = __DIR__ . '/../../src/Database/Schema/SqlServerSchema.php';
        $this->assertFileExists($this->sourcePath);
        $this->contents = file_get_contents($this->sourcePath);
    }

    /**
     * Slice the body of a named protected/private method.
     */
    private function methodBody(string $methodName): string
    {
        $start = strpos($this->contents, "function {$methodName}(");
        $this->assertNotFalse($start, "method {$methodName}() must exist");
        $next = strpos($this->contents, 'function ', $start + 10);
        return substr($this->contents, $start, $next === false ? null : ($next - $start));
    }

    public function testGetTableNamesUsesParameterizedSchema(): void
    {
        $body = $this->methodBody('getTableNames');
        $this->assertDoesNotMatchRegularExpression(
            "/TABLE_SCHEMA\s*=\s*'\\\$schema'/",
            $body,
            "getTableNames() must not interpolate '\$schema' into SQL"
        );
        // After fix: a `:schema` placeholder appears AND `select(...)` is
        // invoked with a bindings array.
        $hasNamedPlaceholder = preg_match('/TABLE_SCHEMA\s*=\s*:schema/', $body) === 1;
        $hasQuestionPlaceholder = preg_match('/TABLE_SCHEMA\s*=\s*\?/', $body) === 1;
        $this->assertTrue(
            $hasNamedPlaceholder || $hasQuestionPlaceholder,
            'getTableNames() must use :schema (or ?) placeholder'
        );
    }

    public function testGetViewNamesUsesParameterizedSchema(): void
    {
        $body = $this->methodBody('getViewNames');
        $this->assertDoesNotMatchRegularExpression(
            "/TABLE_SCHEMA\s*=\s*'\\\$schema'/",
            $body,
            "getViewNames() must not interpolate '\$schema' into SQL"
        );
        $hasNamedPlaceholder = preg_match('/TABLE_SCHEMA\s*=\s*:schema/', $body) === 1;
        $hasQuestionPlaceholder = preg_match('/TABLE_SCHEMA\s*=\s*\?/', $body) === 1;
        $this->assertTrue(
            $hasNamedPlaceholder || $hasQuestionPlaceholder,
            'getViewNames() must use :schema (or ?) placeholder'
        );
    }

    public function testGetTableConstraintsUsesParameterizedSchema(): void
    {
        $body = $this->methodBody('getTableConstraints');
        $this->assertDoesNotMatchRegularExpression(
            "/IN\s*\(\s*'\{\\\$schema\}'\s*\)/",
            $body,
            "getTableConstraints() must not interpolate \$schema into the IN list"
        );
    }

    public function testLoadParametersUsesParameterizedRoutineAndSchema(): void
    {
        $body = $this->methodBody('loadParameters');
        $this->assertDoesNotMatchRegularExpression(
            "/'\{\\\$holder->resourceName\}'/",
            $body,
            'loadParameters() must not interpolate $holder->resourceName'
        );
        $this->assertDoesNotMatchRegularExpression(
            "/'\{\\\$holder->schemaName\}'/",
            $body,
            'loadParameters() must not interpolate $holder->schemaName'
        );
        $this->assertMatchesRegularExpression(
            '/:(routineName|schemaName|name|schema)\b/',
            $body,
            'loadParameters() must use named placeholders'
        );
    }
}
