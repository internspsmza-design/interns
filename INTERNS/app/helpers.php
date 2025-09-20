<?php
function h($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function url($path){$base=(require __DIR__.'/config.php')['APP_BASE'];return $base.$path;}
