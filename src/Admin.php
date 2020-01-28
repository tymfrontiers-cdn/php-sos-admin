<?php
namespace SOS;
use \TymFrontiers\Data,
    \TymFrontiers\Generic;

class Admin{
  use \TymFrontiers\Helper\MySQLDatabaseObject,
      \TymFrontiers\Helper\Pagination;

  protected static $_primary_key='user';
  protected static $_db_name=MYSQL_ADMIN_DB;
  protected static $_table_name="admin";
	protected static $_db_fields = [
    "user",
    "active",
    "work_group",
    "_author",
    "_created"
  ];
  protected static $_prop_type = [];
  protected static $_prop_size = [];

  const PREFIX = "ADM.";
  const SURFIX = ".ADM";

  public $user;
  public $active = false;
  public $work_group;

  protected $_created;
  protected $_author;

  public $errors = [];
  public $profile = null;

  public static function init(string $user){
    return (new \TymFrontiers\Validator() )->username($user,["user","username",3,12])
    ? self::_objtize($user) : false;
  }


  // private/protected methods
  private static function _objtize(string $id){
    if ($found = self::findById($id)) {
      $profile = User::find($found->user, 'id');
      $found->profile = $profile ? $profile[0] : null;
      return $found;
    }
    return false;
  }
  public function _create() { return false; }
}
