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

class AphrontDefaultApplicationController extends AphrontController {

  public function processRequest() {
    $request = $this->getRequest();

    $path = phutil_escape_html($request->getPath());
    $host = phutil_escape_html($request->getHost());
    $controller_name = phutil_escape_html(get_class($this));

    $response = new AphrontWebpageResponse();
    $response->setContent(<<<EOPAGE
<html>
  <head>
    <title>Aphront Default Application</title>
  </head>
  <body>
    <h1>Welcome to Aphront</h1>
    <p>Things appear to be working properly.</p>
    <h2>Request Information</h2>
    <table>
      <tr>
        <th>Host</th><td>{$host}</td>
      </tr>
      <tr>
        <th>Path</th><td>{$path}</td>
      </tr>
      <tr>
        <th>Controller</th><td>{$controller_name}</td>
      </tr>
    </table>
  </body>
</html>

EOPAGE
    );

    return $response;
  }

}
