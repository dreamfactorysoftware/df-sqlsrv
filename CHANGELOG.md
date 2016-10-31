# Change Log
All notable changes to this project will be documented in this file.
This project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]
### Added

### Changed
- Virtual relationship rework to support all relationship types

### Fixed

## [0.5.0] - 2016-10-03
- Update to latest df-core and df-sqldb changes

## [0.4.1] - 2016-09-21
### Fixed
- Column schema fixes for underlying changes of dblib driver compiled with PHP7 (string to number booleans)

## [0.4.0] - 2016-08-21
### Changed
- General cleanup from declaration changes in df-core for service doc and providers

## [0.3.2] - 2016-07-28
### Fixed
- Fix parameter type declaration for dblib binding workaround, string length not included

## [0.3.1] - 2016-07-08
### Added
- DF-636 Adding ability using 'ids' parameter to return the schema of a stored procedure or function

### Fixed
- Bug fixes on stored procedures and functions
- SQL Server requires bound params to conform to designated type

## 0.3.0 - 2016-05-27
First official release working with the new [df-core](https://github.com/dreamfactorysoftware/df-core) library.

[Unreleased]: https://github.com/dreamfactorysoftware/df-sqlsrv/compare/0.5.0...HEAD
[0.5.0]: https://github.com/dreamfactorysoftware/df-sqlsrv/compare/0.4.1...0.5.0
[0.4.1]: https://github.com/dreamfactorysoftware/df-sqlsrv/compare/0.4.0...0.4.1
[0.4.0]: https://github.com/dreamfactorysoftware/df-sqlsrv/compare/0.3.2...0.4.0
[0.3.2]: https://github.com/dreamfactorysoftware/df-sqlsrv/compare/0.3.1...0.3.2
[0.3.1]: https://github.com/dreamfactorysoftware/df-sqlsrv/compare/0.3.0...0.3.1
