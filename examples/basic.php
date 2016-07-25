<?php
require_once __DIR__ . '/../vendor/autoload.php';

use mbaynton\CliEditorLauncher;

$orig_text = <<<EOF
Here's a big block of multi-line text that we would like to allow our users to
view and update in their preferred text editor.

The CliEditorLauncher class will probe the system for well-known editors and use
the EDITOR environment variable to find possible editors to use. If it finds
several possibilities, it will ask the user which they prefer.

EOF;



$editor = new CliEditorLauncher();
$result = $editor->editString($orig_text, "# Make your changes above.\n# This is a test.\n");
//$result = $editor->editString($orig_text);
if ($result->isChanged()) {
  print "New input processed:\n";
  print $result;
} else {
  print "No change.\n";
}
