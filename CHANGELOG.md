Yii 2 Install extension Change Log
==================================

1.0.3, March 2, 2018
--------------------

- Bug #5: Fixed 'error' and 'warning' log level is not verbose at console output (klimov-paul)
- Enh: `InitController` updated to use `yii\console\ExitCode` for exit code specification (klimov-paul)


1.0.2, January 12, 2017
-----------------------

- Bug #4: Fixed invalid project path in the confirm message at `InitController::actionAll()` (klimov-paul)


1.0.1, February 10, 2016
------------------------

- Bug #2: `InitController::actionRequirements()` prevents installation on warning in case requirements checking is performed by output analyzes (klimov-paul)
- Enh #3: `InitController::$commands` now allows usage of callable (klimov-paul)


1.0.0, December 29, 2015
------------------------

- Initial release.
