<?php

/*
 * Copyright 2011 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */


class PhutilErrorHandler {

  private static $errorListener = null;

  const EXCEPTION = 'exception';
  const ERROR = 'error';
  const PHLOG = 'phlog';

  /**
   * Registers this class as the error and exception handler
   */
  public static function initialize() {
    set_error_handler(array('PhutilErrorHandler', 'handleError'));
    set_exception_handler(array('PhutilErrorHandler', 'handleException'));
  }

  public static function setErrorListener($listener) {
    self::$errorListener = $listener;
  }

  /**
   * Handles PHP errors and dispatches them forward.
   */
  public static function handleError($num, $str, $file, $line, $ctx) {
    $trace = debug_backtrace();
    array_shift($trace);
    self::dispatchErrorMessage(
      self::ERROR,
      $str,
      array('file'       => $file,
            'line'       => $line,
            'context'    => $ctx,
            'error_code' => $num,
            'trace'      => $trace));
  }

  /**
   * Handles PHP exceptions and dispatches them forward.
   */
  public static function handleException($ex) {
    self::dispatchErrorMessage(
      self::EXCEPTION,
      $ex,
      array('trace' => debug_backtrace()));
  }

  /**
   * Outputs a stacktrace to error log
   */
  public static function outputStacktrace($trace) {
    foreach ($trace as $key => $entry) {
      $line = '  #'.$key.' ';
      if (isset($entry['class'])) {
        $line .= $entry['class'].'::';
      }
      $line .= idx($entry, 'function', '');

      if (isset($entry['args'])) {
        $args = array();
        foreach ($entry['args'] as $arg) {
          $args[] = PhutilReadableSerializer::printShort($arg);
        }
        $line .= '('.implode(', ', $args).')';
      }

      if (isset($entry['file'])) {
        $line .= ' called at ['.$entry['file'].':'.$entry['line'].']';
      }
      error_log($line);
    }
  }

  /**
   * All different type of error messages come here before they are
   * dispatched to the listener
   */
  public static function dispatchErrorMessage($event, $value, $metadata) {
    $timestamp = strftime("%F %T");

    switch ($event) {
      case PhutilErrorHandler::ERROR:
        if (error_reporting() === 0) {
          // Respect the use of "@" to silence warnings: if this error was
          // emitted from a context where "@" was in effect, the
          // value returned by error_reporting() will be 0. This is the
          // recommended way to check for this, see set_error_handler() docs
          // on php.net.
          break;
        }
        error_log(sprintf(
                    "[%s] ERROR %d: %s at [%s:%d]",
                    $timestamp,
                    $metadata['error_code'],
                    $value,
                    $metadata['file'],
                    $metadata['line']));
        self::outputStacktrace($metadata['trace']);
        break;
      case PhutilErrorHandler::EXCEPTION:
        error_log(sprintf(
                    "[%s] EXCEPTION: %s at [%s:%d]",
                    $timestamp,
                    $value->getMessage(),
                    $value->getFile(),
                    $value->getLine()));
        break;
      case PhutilErrorHandler::PHLOG:
        error_log(sprintf(
                    "[%s] PHLOG: %s at [%s:%d]",
                    $timestamp,
                    PhutilReadableSerializer::printShort($value),
                    $metadata['file'],
                    $metadata['line']));
        break;
      default:
        error_log('Unknown event '.$event);
        break;
    }

    if (self::$errorListener) {
      static $handling_error;
      if ($handling_error) {
        error_log(
          "Error handler was reentered, some errors were not ".
          "passed to listeners.");
        return;
      }
      $handling_error = true;
      call_user_func(self::$errorListener, $event, $value, $metadata);
      $handling_error = false;
    }
  }
} PhutilErrorHandler::initialize();


/**
 * Phabricator log function for personal debugging. Takes any type of
 * argument and eventually the message will be forwared to a listener
 * (DarkConsole)
 */
function phlog($value) {

  // Get the caller information
  $trace = debug_backtrace();
  $file = $trace[0]['file'];
  $line = $trace[0]['line'];

  PhutilErrorHandler::dispatchErrorMessage(
    PhutilErrorHandler::PHLOG,
    $value,
    array('file'  => $file,
          'line'  => $line,
          'trace' => $trace));
}

