<?php
require_once __DIR__ . '/../vendor/autoload.php';

use mbaynton\CliEditorLauncher;

$editor = new CliEditorLauncher();
$result = $editor->editFile(__DIR__ . DIRECTORY_SEPARATOR . 'sample.txt', "You can make changes to the above text.\nThis is a test.\n");

if ($result->isChanged()) {
  print "New input processed:\n";
  print $result;
} else {
  print "No change.\n";
}
