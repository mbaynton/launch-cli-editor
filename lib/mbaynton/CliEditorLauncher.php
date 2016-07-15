<?php

namespace mbaynton;

use \cli;
use \mbaynton\LaunchCliEditor\Result;

class CliEditorLauncher {

  /**
   * @var
   *   Multidimensional array of editors found on this system by type (term,X)
   *   and path.
   */
  protected $editorCache = NULL;
  protected $tmpFileName = NULL;
  protected $tmpFile = NULL;

  /**
   * @var null|string
   *   Path to an editor as discovered from environment variable(s)
   */
  protected $envVarEditor = NULL;

  /**
   * @var null|string
   *   Name of an environment variable storing the preferred editor specifically
   *   for this script.
   */
  protected $envVarEditorVarName = NULL;

  protected $preferredEditor = NULL;

  protected static $defaultTerminalEditorPaths = [
    '/usr/bin/vim' => 'vi',
    '/usr/bin/emacs' => 'emacs',
    '/usr/bin/nano' => 'nano',
  ];

  protected static $defaultXEditorPaths = [
    '/usr/bin/gedit' => 'gedit',
  ];

  /**
   * CliEditorLauncher constructor.
   * @param array $opts
   *   Options to the launcher as an associative array, documented below:
   *   EditorEnvVar       Name of an environment variable to check for the path
   *                      to the user's preferred editor. In addition, EDITOR is
   *                      always checked.
   */
  public function __construct($opts = []) {
    if (! empty($opts['EditorEnvVar']) && getenv($opts['EditorEnvVar'])) {
      $this->envVarEditor = getenv($opts['EditorEnvVar']);
      $this->envVarEditorVarName = $opts['EditorEnvVar'];
    } else if (getenv('EDITOR')) {
      $this->envVarEditor = getenv('EDITOR');
    }
  }

  public function editString($input) {
    $tmp_f = $this->prepTempFile();
    if (! empty($input) || is_numeric($input)) {
      fwrite($tmp_f, $input);
    }

    return $this->launchEditor($this->tmpFileName, TRUE);
  }

  public function editFile($filename) {
    return $this->launchEditor($filename, FALSE);
  }

  protected function launchEditor($filename, $auto_unlink) {
    $orig = md5_file($filename, FALSE);
    $edited = FALSE;
    $banned_editors = [];
    while (! $edited) {
      $editor_binary = $this->locatePreferredEditor($banned_editors);
      if (self::spawnProcess($editor_binary, [$filename]) !== 0) {
        // if we managed to edit the file anyway, ignore the exit code
        if (strcmp(md5_file($filename, FALSE), $orig) === 0) {
          cli\out('The editor "' . $editor_binary . '" does not appear to have run successfully. ');
          if (cli\prompt('Choose another editor [y/N]', 'y', '? ') != 'N') {
            $banned_editors[] = $editor_binary;
            $this->clearPreferredEditor();
          } else {
            $edited = TRUE;
          }
        } else {
          $edited = TRUE;
        }
      } else {
        $edited = TRUE;
      }
    }

    return new Result($edited, $filename, $orig, $auto_unlink);
  }

  /**
   * @param array $banned_editors
   *   Indexed array containing full paths to editors that have failed.
   * @return string
   *   Full path to the preferred editor to use.
   */
  protected function locatePreferredEditor($banned_editors = []) {
    // Use environment variable settings without prompting if they're going to
    // work.
    if ($this->preferredEditor && ! in_array($this->preferredEditor, $banned_editors)) {
      return $this->preferredEditor;
    } else if ($this->envVarEditor && ! in_array($this->envVarEditor, $banned_editors)) {
      $this->preferredEditor = $this->envVarEditor;
    } else {
      $available_editors = $this->locateAvailableEditors();
      $filtered = [];
      foreach ($available_editors['X'] as $path => $name) {
        if (! in_array($path, $banned_editors)) {
          $filtered[$path] = $name;
        }
      }
      $available_editors['X'] = $filtered;

      $filtered = [];
      foreach ($available_editors['term'] as $path => $name) {
        if (! in_array($path, $banned_editors)) {
          $filtered[$path] = $name;
        }
      }
      $available_editors['term'] = $filtered;

      $num = count($available_editors['X']) + count($available_editors['term']);

      if ($num == 1) {
        $this->preferredEditor = current(array_keys(array_merge($available_editors['X'], $available_editors['term'])));
      } else if ($num == 0) {
        cli\out('No text editor was found at common locations. ');
        $editor = false;
        while (! file_exists($editor)) {
          $editor = cli\prompt('Enter the path to your text editor');
        }
      } else {
        $choices = array_map(function($item) { return $item . ' (GUI)'; }, $available_editors['X']);
        $choices += $available_editors['term'];
        cli\out("The following text editors were found on your system:\n");
        $this->preferredEditor = cli\menu($choices, current(array_keys($choices)), 'Which editor do you prefer?');
      }
    }

    return $this->preferredEditor;
  }

  public function clearPreferredEditor() {
    $this->envVarEditor = NULL;
    $this->preferredEditor = NULL;
  }

  public function locateAvailableEditors() {
    if ($this->editorCache === NULL) {
        $this->buildEditorCacheFromDefaults(self::$defaultTerminalEditorPaths, $this->editorCache['term']);
        if (strlen(getenv('DISPLAY'))) {
          $this->buildEditorCacheFromDefaults(self::$defaultXEditorPaths, $this->editorCache['X']);
        }
    }

    return $this->editorCache;
  }

  protected function buildEditorCacheFromWhich($defaultEditors, &$cache) {
    $bin_names = array_map(function($abspath) { return basename($abspath); }, array_keys($defaultEditors));

    $which_output = $this->prepTempFile();
    $this->spawnProcess('/usr/bin/which', $bin_names, [1 => $which_output]);
    fseek($which_output, 0);

    while($abspath = fgets($which_output)) {
      $cache[trim($abspath)] = basename(trim($abspath));
    }
  }

  protected function buildEditorCacheFromDefaults($defaultEditors, &$cache) {
    foreach ($defaultEditors as $abspath => $name) {
      if (file_exists($abspath)) {
        $cache[$abspath] = $name;
      }
    }
  }

  protected function prepTempFile() {
    if ($this->tmpFileName === NULL) {
      $this->tmpFileName = tempnam(sys_get_temp_dir(), 'php_editabletmp_');
      $this->tmpFile = fopen($this->tmpFileName, 'w+');

      if (! $this->tmpFile) {
        throw new \RuntimeException('Could not create needed temp file.');
      }

    }

    ftruncate($this->tmpFile, 0);
    return $this->tmpFile;
  }

  /**
   * Spawns a new process, hands off our STDIN/OUT/ERR to it, blocks waiting
   * for it to terminate, and returns the exit code.
   *
   * @param string $cmd
   *   The command to use to spawn the new process.
   * @param string[] $args
   *   An array of values to be passed to the process, each as an individual argument using escapeshellarg.
   * @param resource[] $streams
   *   An array of streams to override what STDIN, OUT, or ERR are attached to, if desired.
   *   Use index 0 for STDIN, 1 for STDOUT, 2 for STDERR, and provide a stream resource.
   * @return int
   *   The exit code of the process that was spawned.
   */
  protected function spawnProcess($cmd, $args = [], $streams = NULL) {
    if ($streams === NULL) {
      $streams = [];
    }
    if (! is_array($streams)) {
      trigger_error('Parameter 2 of spawnProcess must be an array. The spawned process will be connected to the default STDIN/OUT/ERR.', E_WARNING);
      $streams = [];
    }

    if (! isset($streams[0])) {
      $streams[0] = STDIN;
    }
    if (! isset($streams[1])) {
      $streams[1] = STDOUT;
    }
    if (! isset($streams[2])) {
      $streams[2] = STDERR;
    }

    $unused = [];

    // add arguments
    foreach ($args as $arg) {
      $cmd .= ' ' . escapeshellarg($arg);
    }

    $h_proc = proc_open($cmd, $streams, $unused);
    return proc_close($h_proc);
  }

}