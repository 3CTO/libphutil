<?php

/*
 * Copyright 2012 Facebook, Inc.
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
 * @group markup
 */
final class PhutilRemarkupEngineRemarkupListBlockRule
  extends PhutilRemarkupEngineBlockRule {

  public function getBlockPattern() {
    // Support either "-" or "*" lists.
    return '/^\s*[-*]\s+/';
  }

  public function shouldMergeBlocks() {
    return true;
  }

  public function markupText($text) {
    $items = preg_split($this->getBlockPattern().'m', $text);
    foreach ($items as $key => $item) {
      if (!strlen($item)) {
        unset($items[$key]);
      } else {
        $items[$key] = '<li>'.$this->applyRules(rtrim($item)).'</li>';
      }
    }
    return "<ul>\n".implode("\n", $items)."\n</ul>";
  }
}
