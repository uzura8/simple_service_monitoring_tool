<?php
require dirname(__FILE__).'/config.php';

$monitor = new Service_Monitor($config);
if (! $monitor->check_accessible_ip())
{
  header('HTTP/1.1 403 Forbidden');
  die('Forbidden');
}

if (! $monitor->check_services()) die(sprintf('Error: %s', $monitor->get_error()));
echo 'OK';


class Service_Monitor
{
  private $error_message = '';
  private $db;

  public function check_services()
  {
    try
    {
      $this->connect_memcache();
      $this->connect_mysql();
      $this->check_mysql_replications();
      return true;
    }
    catch(PDOException $e)
    {
      $this->error_message = 'DB Unconnected';
    }
    catch(Exception $e)
    {
      $this->error_message = $e->getMessage();
    }

    return false;
  }

  public function check_accessible_ip()
  {
    if (!defined('ACCESSIBLE_IP') || !ACCESSIBLE_IP) return true;
    if ($_SERVER['REMOTE_ADDR'] == ACCESSIBLE_IP) return true;

    return false;
  }

  public function get_error()
  {
    return $this->error_message;
  }

  private function connect_memcache()
  {
    if (!defined('MEMCACHE_HOST') || !MEMCACHE_HOST) return;
    if (!defined('MEMCACHE_PORT') || !MEMCACHE_PORT) return;

    $memcache = new Memcache;
    if (!$memcache->connect(MEMCACHE_HOST, MEMCACHE_PORT))
    {
      throw new Exception('Memcache unconnected');
    }
  }

  private function connect_mysql($db_key = 'default')
  {
    if ( empty($GLOBALS['_DB_DSN'][$db_key]['connection']['dsn'])
      || empty($GLOBALS['_DB_DSN'][$db_key]['connection']['username']))
    {
      throw new Exception(sprintf('DB setting error(db:%s)', $db_key));
    }
    $config = $GLOBALS['_DB_DSN'][$db_key]['connection'];

    $this->db = new PDO($config['dsn'], $config['username'], $config['password']);
    $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  }

  private function check_slave_status($db_key)
  {
    $this->connect_mysql($db_key);
    if (!$result = $this->db->query('SHOW SLAVE STATUS'))
    {
      throw new Exception(sprintf('DB error(db:%s)', $db_key));
      //throw new Exception(sprintf('DB error: %s', $this->db->errorInfo()[2]));
    }
    foreach($result->fetchAll() as $row)
    {
      var_dump($row);
    }
  }

  private function check_mysql_replications()
  {
    if (empty($GLOBALS['_DB_DSN']['default']['readonly'])) return;
    foreach ($GLOBALS['_DB_DSN']['default']['readonly'] as $db_key)
    {
      $this->check_slave_status($db_key);
    }
  }
}

