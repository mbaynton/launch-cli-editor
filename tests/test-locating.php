<?php

use \mbaynton\CliEditorLauncher;

class Test_Locating extends PHPUnit_Framework_TestCase {

  /**
   * Without a lot more mocking we're pretty dependent on the particular
   * filesystem and 'which'. We can at least check for errors.
   */
  function testEditorLocationProbe() {
    $sysInTest = new CliEditorLauncher();

    $editors = $sysInTest->locateAvailableEditors();

    // locateAvailableEditors() caches, let's just make sure we're not able to
    // pollute the returned array.
    $editors['xyx'] = 1;
    $editors = $sysInTest->locateAvailableEditors();
    $this->assertArrayNotHasKey('xyx', $editors, 'Editors array cannot be polluted.');
  }

}