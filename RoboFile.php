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
  const PROJECT_HCMS = 'hcms';
  const PROJECT_CRM = 'crm';
  const PROJECT_AO = 'ao';
  const PROJECT_ST = 'st';

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

  public function initHIS($opts = self::OPTS) {
    $this->say("Init HIS project");

    $env = $opts['environment'];
    $site = $opts['site'];

    if(!in_array($env, $this->envs)) {
      throw new \Exception("L'ambiente " . $env . " non esiste.");
    }

    // solr volume permission
    $this->init(self::PROJECT_AO, $env, $site);
    $this->setupSolrVolumePermission(self::PROJECT_AO);
    $this->init(self::PROJECT_ST, $env, $site);
    $this->setupSolrVolumePermission(self::PROJECT_ST);
  }

  /**
   * Build HCMS project.
   *
   * @param array $opts
   * @return void
   */
  public function buildHcms($opts = self::OPTS) {
    $this->say("Build HCMS");
    
    $env = $opts['environment'];
    $site = $opts['site'];

    if(!in_array($env, $this->envs)) {
      throw new \Exception("L'ambiente " . $env . " non esiste.");
    }

    $this->init(self::PROJECT_HCMS, $env, $site);
    $this->backupDBProject(self::PROJECT_HCMS);
    $this->importDBProject(self::PROJECT_HCMS);

    // Custom setup for HCMS
    // 1° Setup elasticsearch
    $this->setupElasticSearchHCMS();
    $this->setupDrupalAccount(self::PROJECT_HCMS);
    $this->setupDrupalFileTemporayPath(self::PROJECT_HCMS);
  }

  /**
   * Build CRM project.
   *
   * @param array $opts
   * @return void
   */
  public function buildCrm($opts = self::OPTS) {
    $this->say("Build Crm");
    
    $env = $opts['environment'];
    $site = $opts['site'];

    if(!in_array($env, $this->envs)) {
      throw new \Exception("L'ambiente " . $env . " non esiste.");
    }

    $this->init(self::PROJECT_CRM, $env, $site);
    $this->backupDBProject(self::PROJECT_CRM);
    $this->importDBProject(self::PROJECT_CRM);

    // Custom setup for CRM
    $this->setupDrupalAccount(self::PROJECT_CRM);
    $this->setupDrupalFileTemporayPath(self::PROJECT_HCMS);
  }

  /**
   * Build Area Operatori project.
   *
   * @param array $opts
   * @return void
   */
  public function buildAO($opts = self::OPTS) {
    $this->say("Build Area Operatori");
    
    $env = $opts['environment'];
    $site = $opts['site'];

    if(!in_array($env, $this->envs)) {
      throw new \Exception("L'ambiente " . $env . " non esiste.");
    }

    $this->init(self::PROJECT_AO, $env, $site);
    $this->backupDBProject(self::PROJECT_AO);
    $this->importDBProject(self::PROJECT_AO);

    // Custom setup for Area Operatori
    $this->setupDrupalAccount(self::PROJECT_AO);
    $this->setupDrupalFileTemporayPath(self::PROJECT_HCMS);
    $this->setupCustomAO();
  }

  /**
   * Build Sardegna Turismo project.
   *
   * @param array $opts
   * @return void
   */
  public function buildST($opts = self::OPTS) {
    $this->say("Build Sardegna Turismo");

    $env = $opts['environment'];
    $site = $opts['site'];

    if(!in_array($env, $this->envs)) {
      throw new \Exception("L'ambiente " . $env . " non esiste.");
    }

    $this->init(self::PROJECT_ST, $env, $site);
    $this->backupDBProject(self::PROJECT_ST);
    $this->importDBProject(self::PROJECT_ST);

    // Custom setup for Sardegna Turismo
    $this->setupDrupalAccount(self::PROJECT_ST);
    $this->setupDrupalFileTemporayPath(self::PROJECT_ST);
    $this->setupCustomST();
  }

  /**
   * Configure hcms to point to local elasticsearch instance.
   */
  private function setupElasticSearchHCMS() {
    $this->say('Point HCMS to local ElastichSearch.');
    $database = Robo::Config()->get(self::PROJECT_HCMS . '.databases.drupal');
    $user = $database['user'];
    $password = $database['password'];
    $host = $database['host'];
    $port = $database['port'];
    $dbname = $database['dbname'];

    $sql = "UPDATE his_elasticsearch_connector_cluster set url = \"http://elasticsearch:9200\" WHERE cluster_id = \"elasticcluster\";";

    $command_sql = "mysql -u " . $user . " -p" . $password . 
      " -P " . $port . " -h " . $host . " " . $dbname .  " -e '".$sql."'";
    $this->taskExec($command_sql)->run();
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
   * Custom setup for ST.
   *
   * @return void
   */
  private function setupCustomST() {
    // local solr configuration
    $this->setupLocalSolrServerForST();
  }

  /**
   * Create a local solr server for ST and associate to it all indexes.
   */
  private function setupLocalSolrServerForST() {
    $drush_container = Robo::Config()->get('st.drush_container');
    $command_bash = "scripts/SardegnaTurismoCreateLocalSolrServer.drush";
    $command = "docker exec -it " . $drush_container . " sh -c '" . $command_bash . "'";

    $this->say("Set local solr settings for project Sardegna Turismo");
    $this->taskExec($command)->run();
  }

  /**
   * Copy accreditation role file in private directory.
   *
   * @return void
   */
  private function copyAccreditationRoleFile() {
    $this->say("Copy accreditation role file in private directory");

    $to = $this->getBasePath() . "/" . 
      $this->getProjectRootDirectory(self::PROJECT_AO) . "/" . 
      $this->getProjectCodeDirectory(self::PROJECT_AO) . "/" .
      Robo::Config()->get(self::PROJECT_AO . '.drupal.private') . "/" . 
      Robo::Config()->get(self::PROJECT_AO . '.drupal.accreditation_filename');
    $setting = self::PROJECT_AO . "-settings";
    $from = $this->getBasePath() . "/" . self::FOLDER_DATA . "/" . $setting . "/" . Robo::Config()->get(self::PROJECT_AO . '.drupal.accreditation_filename');

    $this->taskFilesystemStack()->copy($from, $to)->run();
  }

  /**
   * Set up drupal variables for extra_utils.
   *
   * @return void
   */
  private function setExtraUtilsApiRestAnagrafe() {
    $variables = [
      'extra_utils_apirestanagrafe',
      'extra_utils_apirestanagrafe_nuovoidras',
      'extra_utils_apirestanagrafe_new',
      'extra_utils_apirestanagrafe_edit',
      'extra_utils_apirestanagrafe_newdocuments',
    ];
    $drush_container = Robo::Config()->get(self::PROJECT_AO . '.drush_container');

    foreach($variables as $variable) {
      $command_drush = "drush vset " . $variable . " 'http://127.0.0.1'";
      $command = "docker exec -it " . $drush_container . " " . $command_drush;
      $this->taskExec($command)->run();
    }
  }

  /**
   * Execute databases backup for a hyperlocal project
   *
   * @param string $project
   *   hyperlocal project id.
   */
  private function backupDBProject($project = self::PROJECT_HCMS) {
    
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
   * Execute databases import for a hyperlocal project
   *
   * @param string $project
   *   hyperlocal project id.
   */
  private function importDBProject($project = self::PROJECT_HCMS) {

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
   * Setup ras-admin password for a hyperlocal project.
   *
   * @param string $project
   *   hyperlocal project id.
   */
  private function setupDrupalAccount($project = self::PROJECT_HCMS) {
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
  private function setupDrupalFileTemporayPath($project = self::PROJECT_HCMS) {
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
  private function setupSolrVolumePermission($project = self::PROJECT_ST) {
    $drush_container = Robo::Config()->get($project . '.drush_container');
    $command_bash = "chown 1000.1000 /opt/solr/example/solr/data/ -R";
    $command = "docker exec -it " . $drush_container . " sh -c '" . $command_bash . "'";

    $this->say("Set solr volume ownership for project: " . $project);
    $this->taskExec($command)->run();
  }

  /**
   * Undocumented function
   *
   * @param [type] $project
   * @return void
   */
  private function createDrupalPrivateDirectory($project = self::PROJECT_HCMS) {
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

  /**
   * Retrieve a DrushStack.
   *
   * @return \Boedah\Robo\Task\Drush\DrushStack
   *   Retrieve an object DrushStack with the root folder set.
   */
  private function getDrush() {
    /** @var \Boedah\Robo\Task\Drush\DrushStack $drush_stack */
    $drush_stack = $this->task(\Boedah\Robo\Task\Drush\DrushStack::class, Robo::Config()->get('drush_path'));
    $drush_stack->drupalRootDirectory($this->getBasePath() . "/" . Robo::Config()->get('site_configuration.root'));
    return $drush_stack;
  }

  /**
   * Setup file and directory for installation.
   *
   * Include:
   *  - clear file and directory site;
   *  - init file and directory site.
   */
  private function setupInstallation() {
    $this->clearFilesystem();
    $this->initFilesystem();
  }

  /**
   * Clears the directory structure for site.
   */
  private function clearFilesystem() {
    $this->say('Clears the directory structure for site');
    $base_path = $this->getSiteRoot() . "/sites/" . Robo::Config()->get('site_configuration.sub_dir');
    $this->taskFilesystemStack()
      ->chmod($base_path, 0775, 0000, TRUE)
      ->chmod($base_path, 0775)
      ->remove($base_path . '/files')
      ->remove($base_path . '/settings.php')
      ->remove($base_path . '/services.yml')
      ->run();
  }

    /**
   * Creates the directory structure for site.
   */
  private function initFilesystem() {
    $this->say('Creates the directory structure for site');
    $base_path = $this->getSiteRoot() . "/sites/" . Robo::Config()->get('site_configuration.sub_dir');
    $this->taskFilesystemStack()
      ->chmod($base_path, 0775, 0000, TRUE)
      ->mkdir($base_path . '/files')
      ->mkdir($base_path . '/files/logs')
      ->chmod($base_path . '/files', 0775, 0000, TRUE)
      ->copy($base_path . '/default.settings.php', $base_path . '/settings.php')
      ->copy($base_path . '/default.services.yml', $base_path . '/services.yml')
      ->run();
  }

  /**
   * Install Drupal.
   *
   * @param array $properties_custom
   *   Properties custom. es. $properties['site_configuration']['profile'].
   */
  private function install($properties_custom = []) {
    $this->say('Install Drupal');
    $properties = $this->properties;
    // User replace and not merge because merge duplicate entry with same key.
    // @see http://php.net/manual/en/function.array-replace-recursive.php
    $properties = array_replace_recursive($properties, $properties_custom);
    $this->getDrush()
      ->siteName(Robo::Config()->get('site_configuration.name'))
      ->siteMail(Robo::Config()->get('site_configuration.mail'))
      ->accountMail(Robo::Config()->get('account.mail'))
      ->accountName(Robo::Config()->get('account.name'))
      ->accountPass(Robo::Config()->get('account.pass'))
      ->dbUrl(Robo::Config()->get('databases.default.url'))
      ->locale(Robo::Config()->get('site_configuration.locale'))
      ->sitesSubdir(Robo::Config()->get('site_configuration.sub_dir'))
      ->siteInstall(Robo::Config()->get('site_configuration.profile'))
      ->run();
  }
}