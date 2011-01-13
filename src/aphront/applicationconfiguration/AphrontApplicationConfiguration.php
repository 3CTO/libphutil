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

abstract class AphrontApplicationConfiguration {

  private $request;
  private $host;
  private $path;

  abstract public function getApplicationName();
  abstract public function getURIMap();
  abstract public function buildRequest();

  final public function setRequest(AphrontRequest $request) {
    $this->request = $request;
    return $this;
  }

  final public function getRequest() {
    return $this->request;
  }

  final public function buildController() {
    $map = $this->getURIMap();
    $mapper = new AphrontURIMapper($map);
    $request = $this->getRequest();
    $path = $request->getPath();
    list($controller_class, $uri_data) = $mapper->mapPath($path);

    PhutilSymbolLoader::loadClass($controller_class);
    $controller = newv($controller_class, array($request));

    return array($controller, $uri_data);
  }

  final public function setHost($host) {
    $this->host = $host;
    return $this;
  }

  final public function getHost() {
    return $this->host;
  }

  final public function setPath($path) {
    $this->path = $path;
    return $this;
  }

  final public function getPath() {
    return $this->path;
  }

}
