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

class PhutilMySQLDatabaseConnection extends PhutilDatabaseConnection {

  private $config;
  private $connection;

  public function __construct(array $configuration) {
    $this->configuration  = $configuration;
  }

  public function escapeString($string) {
    if (!$this->connection) {
      $this->establishConnection();
    }
    return mysql_real_escape_string($string, $this->connection);
  }

  public function escapeColumnName($name) {
    return '`'.str_replace('`', '\\`', $name).'`';
  }

  public function escapeMultilineComment($comment) {
    // These can either terminate a comment, confuse the hell out of the parser,
    // make MySQL execute the comment as a query, or, in the case of semicolon,
    // are quasi-dangerous because the semicolon could turn a broken query into
    // a working query plus an ignored query.

    static $map = array(
      '--'  => '(DOUBLEDASH)',
      '*/'  => '(STARSLASH)',
      '//'  => '(SLASHSLASHL)',
      '#'   => '(HASH)',
      '!'   => '(BANG)',
      ';'   => '(SEMICOLON)',
    );

    $comment = str_replace(
      array_keys($map),
      array_values($map),
      $comment);

    // For good measure, kill anything else that isn't a nice printable
    // character.
    $comment = preg_replace('/[^\x20-\x7F]+/', ' ', $comment);

    return '/* '.$comment.' */';
  }

  public function escapeStringForLikeClause($value) {
    $value = $this->escapeString($value);
    // Ideally the query shouldn't be modified after safely escaping it,
    // but we need to escape _ and % within LIKE terms.
    $value = str_replace(
      // Even though we've already escaped, we need to replace \ with \\
      // because MYSQL unescapes twice inside a LIKE clause. See note
      // at mysql.com. However, if the \ is being used to escape a single
      // quote ('), then the \ should not be escaped. Thus, after all \
      // are replaced with \\, we need to revert instances of \\' back to
      // \'.
      array('\\',   '\\\\\'', '_',  '%'),
      array('\\\\', '\\\'',   '\_', '\%'),
      $value);
    return $value;
  }

  private function getConfiguration($key, $default = null) {
    return idx($this->configuration, $key, $default);
  }

  private function establishConnection() {
    $this->connection = null;

    $conn = @mysql_connect(
      $this->getConfiguration('host'),
      $this->getConfiguration('user'),
      $this->getConfiguration('pass'),
      $new_link = true,
      $flags = 0);

    if (!$conn) {
      throw new PhutilQueryConnectionException();
    }

    $ret = @mysql_select_db($this->getConfiguration('database'), $conn);
    if (!$ret) {
      $this->throwQueryException($conn);
    }

    $this->connection = $conn;
  }

  public function getInsertID() {
    return mysql_insert_id($this->requireConnection());
  }

  public function getAffectedRows() {
    return mysql_affected_rows($this->requireConnection());
  }

  public function getTransactionKey() {
    return (int)$this->requireConnection();
  }

  private function requireConnection() {
    if (!$this->connection) {
      throw new Exception("Connection is required.");
    }
    return $this->connection;
  }

  public function selectAllResults() {
    $result = array();
    $res = $this->lastResult;
    if ($res == null) {
      throw new Exception('No query result to fetch from!');
    }
    while (($row = mysql_fetch_assoc($res)) !== false) {
      $result[] = $row;
    }
    return $result;
  }

  public function executeRawQuery($raw_query) {
    $this->lastResult = null;
    $retries = 3;
    while ($retries--) {
      try {
        if (!$this->connection) {
          $this->establishConnection();
        }

        $result = mysql_query($raw_query, $this->connection);

        if ($result) {
          $this->lastResult = $result;
          break;
        }

        $this->throwQueryException($this->connection);
      } catch (PhutilQueryConnectionLostException $ex) {
        if (!$retries) {
          throw $ex;
        }
        if ($this->isInsideTransaction()) {
          throw $ex;
        }
        $this->connection = null;
      }
    }
  }

  private function throwQueryException($connection) {
    $errno = mysql_errno($connection);
    $error = mysql_error($connection);

    switch ($errno) {
      case 2013: // Connection Dropped
      case 2006: // Gone Away
        throw new PhutilQueryConnectionLostException("#{$errno}: {$error}");
        break;
      case 1213: // Deadlock
      case 1205: // Lock wait timeout exceeded
        throw new PhutilQueryRecoverableException("#{$errno}: {$error}");
        break;
      default:
        // TODO: 1062 is syntax error, and quite terrible in production.
        throw new PhutilQueryException("#{$errno}: {$error}");
    }
  }

}
