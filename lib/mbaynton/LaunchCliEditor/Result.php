<?php

namespace mbaynton\LaunchCliEditor;

/**
 * Class Result
 *   The results of an editor launch, returned after the user has made changes
 *   and exited the editor.
 */
class Result {

  protected $success;
  protected $filename;
  protected $orig_hash;
  protected $auto_unlink;

  protected $fulltext = NULL;

  public function __construct($success, $filename, $orig_hash, $auto_unlink) {
    $this->success = $success;
    $this->filename = $filename;
    $this->orig_hash = $orig_hash;
    $this->auto_unlink = $auto_unlink;
  }

  public function isChanged() {
    $new_hash = md5_file($this->getFilename(), FALSE);
    return strcmp($this->orig_hash, $new_hash) !== 0;
  }

  public function __toString() {
    if ($this->fulltext === NULL) {
      $this->fulltext = file_get_contents($this->getFilename());
    }

    return $this->fulltext;
  }

  public function getFilename() {
    return $this->filename;
  }

  public function unlink() {
    @unlink($this->filename);
  }

  public function __destruct() {
    if ($this->auto_unlink === TRUE) {
      $this->unlink();
    }
  }

}