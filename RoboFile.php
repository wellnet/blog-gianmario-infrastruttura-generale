<?php

use Symfony\Component\Yaml\Yaml;
use Robo\Robo;

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */
class RoboFile extends \Robo\Tasks {

  use \Boedah\Robo\Task\Drush\loadTasks;

  /**
   * Constant variable environment.
   */
  const ENV_LOCAL = 'local';

  /**
   * Constant variable Project.
   */
  const PROJECT_PR1 = 'pr1';
  const PROJECT_PR2 = 'pr2';
  const PROJECT_PR3 = 'pr3';

  /**
   * Options arguments for command line.
   */
  const OPTS = [
    'site|s' => 'default',
    'environment|e' => self::ENV_LOCAL,
  ];

  /**
   * Directory backups and others.
   */
  const FOLDER_PROPERTIES = 'build';
  const FOLDER_TEMPLATES = 'build/templates';
  const FOLDER_DATABASE_BACKUPS = 'build/backups';
  const FOLDER_DATABASE_DUMPS = 'build/dbs';
  const FOLDER_DATA = 'data';

  /**
   * Twig Environment.
   *
   * @var \Twig_Environment
   */
  protected $twig;
  
  /**
   * Store properties used.
   *
   * @var array
   */
  protected $properties = [];

  /**
   * Store environment used.
   *
   * @var string
   */
  protected $environment = 'local';

  /**
   * Store site name used.
   *
   * @var string
   */
  var $site = 'default';

  /**
   * Store if use default site or not.
   *
   * @var bool
   */
  var $use_default = TRUE;

  var $envs = [self::ENV_LOCAL];

  /**
   * Which environments are considered dev.
   *
   * @var array
   */
  var $env_dev = [self::ENV_LOCAL];

  /**
   * RoboFile constructor.
   */
  public function __construct() {
    // Stop a command on first failure of a task.
    $this->stopOnFail(TRUE);
  }

  public function initAG($opts = self::OPTS) {
    $this->say("Init AG project");

    $env = $opts['environment'];
    $site = $opts['site'];

    if(!in_array($env, $this->envs)) {
      throw new \Exception("L'ambiente " . $env . " non esiste.");
    }

    // Call methods to init AG project.
    $this->init(self::PROJECT_PR1, $env, $site);
    $this->setupSolrVolumePermission(self::PROJECT_PR1);
  }

  /**
   * Build PR1 project.
   *
   * @param array $opts
   * @return void
   */
  public function buildPr1($opts = self::OPTS) {
    $this->say("Build PR1");
    
    $env = $opts['environment'];
    $site = $opts['site'];

    if(!in_array($env, $this->envs)) {
      throw new \Exception("L'ambiente " . $env . " non esiste.");
    }

    $this->init(self::PROJECT_PR1, $env, $site);
    $this->backupDBProject(self::PROJECT_PR1);
    $this->importDBProject(self::PROJECT_PR1);

    // Custom setup for HCMS
    // 1° Setup elasticsearch
    $this->setupDrupalAccount(self::PROJECT_PR1);
    $this->setupDrupalFileTemporayPath(self::PROJECT_PR1);
  }

  /**
   * Build PR2 project.
   *
   * @param array $opts
   * @return void
   */
  public function buildPr2($opts = self::OPTS) {
    $this->say("Build PR2");
    
    $env = $opts['environment'];
    $site = $opts['site'];

    if(!in_array($env, $this->envs)) {
      throw new \Exception("L'ambiente " . $env . " non esiste.");
    }

    $this->init(self::PROJECT_PR2, $env, $site);
    $this->backupDBProject(self::PROJECT_PR2);
    $this->importDBProject(self::PROJECT_PR2);

    // Custom setup for PR2
    $this->setupDrupalAccount(self::PROJECT_PR2);
    $this->setupDrupalFileTemporayPath(self::PROJECT_PR2);
  }

  /**
   * Build PR3  project.
   *
   * @param array $opts
   * @return void
   */
  public function buildPr3($opts = self::OPTS) {
    $this->say("Build PR3");
    
    $env = $opts['environment'];
    $site = $opts['site'];

    if(!in_array($env, $this->envs)) {
      throw new \Exception("L'ambiente " . $env . " non esiste.");
    }

    $this->init(self::PROJECT_PR3, $env, $site);
    $this->backupDBProject(self::PROJECT_PR3);
    $this->importDBProject(self::PROJECT_PR3);

    // Custom setup for Area Operatori
    $this->setupDrupalAccount(self::PROJECT_PR3);
    $this->setupDrupalFileTemporayPath(self::PROJECT_PR3);
  }

  /**
   * Custom setup for AO.
   *
   * @return void
   */
  private function setupCustomAO() {
    $this->createDrupalPrivateDirectory(self::PROJECT_AO);
    $this->copyAccreditationRoleFile();
    $this->setExtraUtilsApiRestAnagrafe();
  }

  /**
   * Execute databases backup for a AG project
   *
   * @param string $project
   *   AG project id.
   */
  private function backupDBProject($project = self::PROJECT_PR1) {
    
    $folder = $this->getBasePath() . "/" . self::FOLDER_DATABASE_BACKUPS;
    $this->say('Backup database for ' . $project . '.');
    $databases = Robo::Config()->get($project . '.databases');
    $date = date("Y") . date("m") . date("d") . '_' . date("H") . date("i");
    foreach ($databases as $db_key => $db_values) {
      if($db_values['skip_backup']) {
        $this->say('Skip backup for ' . $db_values['dbname'] . '.');
        continue;
      }

      $dump_filename = $project . '.' . $db_values['dbname'] . '.' . $date . '.sql';
      $dump_filename = $folder . "/" . $dump_filename;
      $user = $db_values['user'];
      $password = $db_values['password'];
      $host = $db_values['host'];
      $port = $db_values['port'];
      $dbname = $db_values['dbname'];
      $command1 = "mysqldump -u " . $user . " -p" . $password . " -P " . $port . " -h " . $host . " -r " . $dump_filename . " " . $dbname;
      $command2 = "gzip " . $dump_filename;
      // $this->say($command);
      $this->say("Backup " . $dbname);
      // esegui il comando:
      $this->taskExec($command1)->run();
      $this->taskExec($command2)->run();
    }
  }

  /**
   * Execute databases import for a AG project
   *
   * @param string $project
   *   AG project id.
   */
  private function importDBProject($project = self::PROJECT_PR1) {

    $folder = $this->getBasePath() . "/" . self::FOLDER_DATABASE_DUMPS;
    $this->say('Import databases for ' . $project . '.');
    $databases = Robo::Config()->get($project . '.databases');
    foreach ($databases as $db_key => $db_values) {
      $dump_filename = $db_values['dump_filename'];
      $dump_filename = $folder . "/" . $dump_filename;

      $user = $db_values['user'];
      $password = $db_values['password'];
      $host = $db_values['host'];
      $port = $db_values['port'];
      $dbname = $db_values['dbname'];
      
      $command_drop = "mysql -u " . $user . " -p" . $password . 
                      " -P " . $port . " -h " . $host . " -e 'DROP DATABASE ".$dbname.";'";
      $command_create = "mysql -u " . $user . " -p" . $password . 
                        " -P " . $port . " -h " . $host . " -e 'CREATE DATABASE ".$dbname.";'";
      $command_import = "mysql -u " . $user . " -p" . $password . " -P " . $port . " -h " . $host . " " . $dbname . " < " . $dump_filename;
      // $this->say($command);
      $this->say("Backup " . $dbname);
      // esegui il comando:
      $this->taskExec($command_drop)->run();
      $this->taskExec($command_create)->run();
      $this->taskExec($command_import)->run();
    }
  }

  /**
   * Setup admin password for a AG project.
   *
   * @param string $project
   *   AG project id.
   */
  private function setupDrupalAccount($project = self::PROJECT_PR1) {
    $drush_container = Robo::Config()->get($project . '.drush_container');
    $account = Robo::Config()->get($project . '.account');
    $username = $account['username'];
    $password = $account['password'];
    $command_drush = "drush upwd ".$username." --password='".$password."'";
    $command = "docker exec -it " . $drush_container . " sh -c '" . $command_drush . "'";

    $this->say("Set ".$username." password");
    $this->taskExec($command)->run();
  }

  /**
   * Setup local tmp file system path.
   *
   * @param string $project
   *   hyperlocal project id.
   */
  private function setupDrupalFileTemporayPath($project = self::PROJECT_PR1) {
    $drush_container = Robo::Config()->get($project . '.drush_container');
    $file_temporary_path = Robo::Config()->get($project . '.drupal.file_temporary_path');
    $command_drush = "drush vset file_temporary_path " . $file_temporary_path;
    $command = "docker exec -it " . $drush_container . " sh -c '" . $command_drush . "'";

    $this->say("Set ".$file_temporary_path." to file_temporary_path variable");
    $this->taskExec($command)->run();
  }

  /**
   * Setup solr volume permission (uid: 1000).
   *
   * @param $project
   *   hyperlocal project id.
   */
  private function setupSolrVolumePermission($project = self::PROJECT_PR1) {
    $solr_container = Robo::Config()->get($project . '.solr_container');
    $command_bash = "chown 1000.1000 /opt/solr/example/solr/data/ -R";
    $command = "docker exec -it -u root " . $solr_container . " sh -c '" . $command_bash . "'";

    $this->say("Set solr volume ownership for project: " . $project);
    $this->taskExec($command)->run();
  }

  /**
   * Create a privat drupal directory.
   *
   * @param [type] $project
   * @return void
   */
  private function createDrupalPrivateDirectory($project = self::PROJECT_PR1) {
    $base_path = $this->getBasePath() . "/";
    $project_directory = $this->getProjectRootDirectory($project) . "/";
    $code_directory = $this->getProjectCodeDirectory($project) . "/";
    $private_directory = $base_path . $project_directory . $code_directory . Robo::Config()->get(self::PROJECT_AO . '.drupal.private');
    
    $this->say($private_directory);

    if (!file_exists($private_directory)) {
      $this->taskFilesystemStack()
        ->mkdir($private_directory)->run();
    }
  }

  /**
   * Init.
   *
   * @param string $environment
   *   Environment variable (local|stage|prod|custom1|..).
   * @param string $site
   *   Match to the sites that you want to start, useful for multi-site.
   *   In the case of a single installation to use/leave 'default'.
   *
   * @throws \Robo\Exception\TaskException
   */
  private function init($project = 'hcms', $environment = 'local', $site = 'default') {
    
    if ($site == 'default' && !$this->use_default) {
      throw new TaskException($this, 'Default site not implement. Please select the site installation.');
    }

    // Set Environment and site for load configuration file.
    $this->setEnvironment($environment);
    $this->setSite($site);
    $this->setPathProperties(self::FOLDER_PROPERTIES);
    Robo::loadConfiguration([__DIR__ . '/' . self::FOLDER_PROPERTIES . '/build.'.$project.".".$environment.'.'.$site.'.yml']);
  }

  /**
   * Renders a template.
   *
   * @param string $template
   *   Template.
   * @param array $variables
   *   Variables.
   *
   * @return string
   *   Template rendered.
   */
  private function templateRender($template, $variables) {
    return $this->twig->render($template, $variables);
  }

  /**
   * Set environment.
   *
   * @param string $environment
   *   Environment.
   */
  private function setEnvironment($environment = 'local') {
    $this->environment = $environment;
  }

  /**
   * Set Site.
   *
   * @param string $site
   *   Site.
   */
  private function setSite($site = 'default') {
    $this->site = $site;
  }

  /**
   * Set PathProperties.
   *
   * @param string $pathProperties
   *   Properties.
   */
  private function setPathProperties($pathProperties) {
    $this->pathProperties = $pathProperties;
  }

  /**
   * Get Base Path (path absolute files).
   *
   * @return string
   *   Path absolute files.
   */
  private function getBasePath() {
    $base_path = Robo::Config()->get('base_path');

    if (empty($base_path)) {
      return __DIR__;
    }

    return $base_path;
  }

  /**
   * Get Project directory name.
   *
   * @param string $project
   *   Project ID.
   *
   * @return string
   *   Project directory name.
   */
  private function getProjectRootDirectory($project) {
    return Robo::Config()->get($project . '.root_directory');
  }

  /**
   * Get Project's code directory name.
   *
   * @param string $project
   *   Project ID.
   *
   * @return string
   *   Project's code directory name.
   */
  private function getProjectCodeDirectory($project) {
    return Robo::Config()->get($project . '.code_directory');
  }
  
  /**
   * Get Site Path (path absolute files).
   *
   * @return string
   *   Path absolute files of root installation.
   */
  private function getSiteRoot() {
    return $this->getBasePath() . "/" . Robo::Config()->get('site_configuration.site_root');
  }

}