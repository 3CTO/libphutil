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

class AphrontRequest {

  const TYPE_AJAX = '__ajax__';

  private $host;
  private $path;
  private $requestData;

  final public function __construct($host, $path) {
    $this->host = $host;
    $this->path = $path;
  }

  final public function setRequestData(array $request_data) {
    $this->requestData = $request_data;
    return $this;
  }

  final public function getPath() {
    return $this->path;
  }

  final public function getHost() {
    return $this->host;
  }

  final public function getInt($name, $default = null) {
    if (isset($this->requestData[$name])) {
      return (int)$this->requestData[$name];
    } else {
      return $default;
    }
  }

  final public function getStr($name, $default = null) {
    if (isset($this->requestData[$name])) {
      return (string)$this->requestData[$name];
    } else {
      return $default;
    }
  }

  final public function getArr($name, $default = null) {
    if (isset($this->requestData[$name]) &&
        is_array($this->requestData[$name])) {
      return $this->requestData[$name];
    } else {
      return $default;
    }
  }

  final public function getExists($name) {
    return array_key_exists($name, $this->requestData);
  }

  final public function isAjax() {
    return $this->getExists(self::TYPE_AJAX);
  }

}
