LswDoctrinePdoDblib Changelog
=============================

Version 1.1.0
-------------
**Attention**: This change may break your code if you are upgrading from 1.0.x. Make sure that you change any `use Lsw\DoctrinePdoDblib\Doctrine\DBAL\Platforms\MsSqlPlatform;` in your code with `use Lsw\DoctrinePdoDblib\Doctrine\Platforms\MsSqlPlatform;`.

* Bugfix: Correct MsSqlPlatform namespace
* Bugfix: Import Doctrine\DBAL\Platforms\AbstractPlatform in MsSqlPlatform
* Added CHANGELOG

Version 1.0.2
-------------
*  Add setter for dateTimeFormatString

Version 1.0.1
-------------
* Add MssSqlPlatform and overwrite getDateTimeFormatString 
* Add charset config

Version 1.0.0
-------------
Initial release