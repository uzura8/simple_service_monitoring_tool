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

  public function check_services()
  {
    try
    {
      $this->check_mysql_connection();
      $this->check_memcache_connection();
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
    if (!defined('ACCESSIBLE_IP' || !ACCESSIBLE_IP)) return true;
    if ($_SERVER['REMOTE_ADDR'] == ACCESSIBLE_IP) return true;

    return false;
  }

  public function get_error()
  {
    return $this->error_message;
  }

  private function connect_memcache()
  {
    $memcache = new Memcache;
    if (!$memcache->connect(MEMCACHE_HOST, MEMCACHE_PORT))
    {
      throw new Exception('Memcache unconnected');
    }
  }

  private function connect_mysql()
  {
    $db = new PDO(DB_PDO_DSN, DB_USERNAME, DB_PASSWORD);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  }
}
