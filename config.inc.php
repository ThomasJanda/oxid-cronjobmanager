<?php

/**
 * where can the system access the php interpreter
 *
 * Normally only "php" but on some hosting provider it can be different
 *
 * Profihost:
 * PHP7.0 => /usr/local/php7.0/bin/php
 * PHP 4.4 => /usr/local/php4/bin/php
 * PHP 5.2 => /usr/local/php5.2/bin/php
 * PHP 5.3 => /usr/local/php5.3/bin/php
 * PHP 5.4 => /usr/local/php5.4/bin/php
 * PHP 5.5 => /usr/local/php5.5/bin/php
 * PHP 5.6 => /usr/local/php5.6/bin/php
 * PHP 7.0 => /usr/local/php7.0/bin/php
 *
 */
define("CRONJOB_MANAGER__PHP_INTERPRETER",     "/usr/local/php7.0/bin/php");


/**
 * if the php cronjob reach time limit or any other error, to which mail address should the system send a message.
 */
define("CRONJOB_MANAGER__MAIL_ADDRESS",        "YOUR@MAILADDRESS.X");