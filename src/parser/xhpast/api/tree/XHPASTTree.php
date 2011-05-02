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


/**
 * @group xhpast
 */
class XHPASTTree {

  protected $tree = array();
  protected $stream = array();
  protected $lineMap;
  protected $rawSource;

  public static function newFromData($php_source) {
    $future = xhpast_get_parser_future($php_source);
    return self::newFromDataAndResolvedExecFuture(
      $php_source,
      $future->resolve());
  }

  public static function newFromDataAndResolvedExecFuture(
    $php_source,
    array $resolved) {

    list($err, $stdout, $stderr) = $resolved;
    if ($err) {
      if ($err == 1) {
        $matches = null;
        $is_syntax = preg_match(
          '/^XHPAST Parse Error: (.*) on line (\d+)/',
          $stderr,
          $matches);
        if ($is_syntax) {
          throw new XHPASTSyntaxErrorException($matches[2], $stderr);
        }
      }
      throw new Exception("XHPAST failed to parse file data {$err}: {$stderr}");
    }

    $data = json_decode($stdout, true);
    if (!is_array($data)) {
      throw new Exception("XHPAST: failed to decode tree.");
    }

    return new XHPASTTree($data['tree'], $data['stream'], $php_source);
  }

  public function __construct(array $tree, array $stream, $source) {
    $ii = 0;
    $offset = 0;

    foreach ($stream as $token) {
      $this->stream[$ii] = new XHPASTToken(
        $ii,
        $token[0],
        substr($source, $offset, $token[1]),
        $offset,
        $this);
      $offset += $token[1];
      ++$ii;
    }

    $this->rawSource = $source;
    $this->buildTree(array($tree));
  }

  /**
   * Unlink internal datastructures so that PHP's will garbage collect the tree.
   * This renders the object useless.
   *
   * @return void
   */
  public function dispose() {
    unset($this->tree);
    unset($this->stream);
  }

  public function getRootNode() {
    return $this->tree[0];
  }

  protected function buildTree(array $tree) {
    $ii = count($this->tree);
    $nodes = array();
    foreach ($tree as $node) {
      $this->tree[$ii] = new XHPASTNode($ii, $node, $this);
      $nodes[$ii] = $node;
      ++$ii;
    }
    foreach ($nodes as $node_id => $node) {
      if (isset($node[3])) {
        $children = $this->buildTree($node[3]);
        foreach ($children as $child) {
          $child->parentNode = $this->tree[$node_id];
        }
        $this->tree[$node_id]->children = $children;
      }
    }

    $result = array();
    foreach ($nodes as $key => $node) {
      $result[$key] = $this->tree[$key];
    }

    return $result;
  }

  public function getRawTokenStream() {
    return $this->stream;
  }

  public function renderAsText() {
    return $this->executeRenderAsText(array($this->getRootNode()), 0);
  }

  protected function executeRenderAsText($list, $depth) {
    $return = '';
    foreach ($list as $node) {
      if ($depth) {
        $return .= str_repeat('  ', $depth);
      }
      $return .= $node->getDescription()."\n";
      $return .= $this->executeRenderAsText($node->getChildren(), $depth + 1);
    }
    return $return;
  }


  public static function evalStaticString($string) {
    $string = '<?php '.rtrim($string, ';').';';
    $tree = XHPASTTree::newFromData($string);
    $statements = $tree->getRootNode()->selectDescendantsOfType('n_STATEMENT');
    if (count($statements) != 1) {
      throw new Exception("String does not parse into exactly one statement!");
    }
    // Return the first one, trying to use reset() with iterators ends in tears.
    foreach ($statements as $statement) {
      return $statement->evalStatic();
    }
  }

  public function getOffsetToLineNumberMap() {
    if ($this->lineMap === null) {
      $src = $this->rawSource;
      $len = strlen($src);
      $lno = 1;
      $map = array();
      for ($ii = 0; $ii < $len; ++$ii) {
        $map[$ii] = $lno;
        if ($src[$ii] == "\n") {
          ++$lno;
        }
      }
      $this->lineMap = $map;
    }
    return $this->lineMap;
  }

}
