<?php
namespace SOS;
use \TymFrontiers\Data,
    \TymFrontiers\Generic,
    \TymFrontiers\Validator;

class Admin{
  use \TymFrontiers\Helper\MySQLDatabaseObject,
      \TymFrontiers\Helper\Pagination;

  protected static $_primary_key='_id';
  protected static $_db_name=MYSQL_ADMIN_DB;
  protected static $_table_name="user";
	protected static $_db_fields = [
    "_id",
    "status",
    "work_group",
    "email",
    "phone",
    "password",
    "name",
    "surname",
    "country_code",
    "state_code",
    "_author",
    "_created"
  ];
  protected static $_prop_type = [];
  protected static $_prop_size = [];

  const PREFIX = "ADM.";
  const SURFIX = ".ADM";

  protected $_id;
  public $status = "PENDING";
  public $work_group = "USER";
  public $email;
  public $phone;
  public $password;
  public $name;
  public $surname;
  public $country_code;
  public $state_code;

  protected $_created;
  protected $_author;

  public $errors = [];

  function __construct (string $id = "") {
    if (
        (new \TymFrontiers\Validator() )->username($id,["id","username",3,12, [], "MIXED"])
        || (new \TymFrontiers\Validator() )->email($id,["email","email"])
      ) {
      $this->_objtize($id);
    }
  }
  // private/protected methods
  private function _objtize(string $id){
    self::_checkEnv();
    global $database;
    $id = $database->escapeValue($id);
    $whost = WHOST;
    $data_db = MYSQL_DATA_DB;
    @ $file_db = MYSQL_FILE_DB;
    @ $file_tbl = MYSQL_FILE_TBL;
    $query = "SELECT usr._id, usr.status, usr.work_group, usr.email,
                     usr.phone, usr.name, usr.surname, usr.country_code, usr.state_code, usr._author, usr._created,
                     CONCAT(ausr.name, ' ', ausr.surname) AS author_name,
                     wg.rank AS access_rank,
                     c.name AS country,
                     st.name AS 'state',
                     (
                        SELECT CONCAT('{$whost}','/file/',f._name)
                      ) AS avatar
              FROM :db:.:tbl: AS usr
              LEFT JOIN :db:.:tbl: AS ausr ON ausr._id = usr._author
              LEFT JOIN :db:.work_group AS wg ON wg.name=usr.work_group
              LEFT JOIN `{$data_db}`.`country` AS c ON c.code = usr.country_code
              LEFT JOIN `{$data_db}`.`state` AS st ON st.code = usr.state_code
              LEFT JOIN `{$file_db}`.`file_default` AS fd ON fd.`user` = usr._id AND fd.set_key = 'ADMIN.AVATAR'
              LEFT JOIN `{$file_db}`.`{$file_tbl}` AS f ON f.id = fd.file_id
              WHERE usr._id = '{$id}'
              OR usr.email = '{$id}'
              LIMIT 1";
    if ($found = self::findBySql($query)) {
      $found = $found[0];
      foreach ($found as $prop => $val) {
        if (\property_exists($this, $prop) && !\in_array($prop, ["password"])) $this->$prop = $val;
      }
      $this->country = $found->country;
      $this->state = $found->state;
      $this->access_rank = $found->access_rank;
      $this->author_name = $found->author_name;
      $this->avatar = (!empty($found->avatar) && Generic::urlExist($found->avatar))
        ? $found->avatar
        : "/app/tymfrontiers-cdn/admin.soswapp/img/default-avatar.png";
    }
  }
  public static function authenticate (string $email, string $password, string $country_code = 'NG') {
    global $database, $access_ranks;
    if (!$database instanceof \TymFrontiers\MySQLDatabase) {
      throw new \Exception("Database not set: '\$database' not instance \TymFrontiers\MySQLDatabase", 1);
    }
    $data = new Data();
    $whost = WHOST;
    $file_db = MYSQL_FILE_DB;
    $file_tbl = MYSQL_FILE_TBL;
    $phone = false;
    $email = $database->escapeValue($email);
    if (@ $phone = $data->phoneToIntl($email, $country_code)) {
      $phone = $database->escapeValue($phone);
    }
    $password = $database->escapeValue($password);
    $sql = "SELECT usr._id, usr._id AS id, usr.status, usr.work_group, usr.email,
            usr.phone, usr.name, usr.surname, usr.password, usr.country_code,
            (
               SELECT CONCAT('{$whost}','/file/',f._name)
             ) AS avatar
            FROM :db:.:tbl: AS usr
            LEFT JOIN `{$file_db}`.`file_default` AS fd ON fd.`user` = usr._id AND fd.set_key = 'ADMIN.AVATAR'
            LEFT JOIN `{$file_db}`.`{$file_tbl}` AS f ON f.id = fd.file_id
            WHERE usr.status IN('ACTIVE')
            AND (
              usr.email = '{$email}'";
          if ($phone) {
            $sql .= " OR usr.phone = '{$phone}' ";
          }
            $sql .= ")
            LIMIT 1";
    $result_array = self::findBySql($sql);
    $record = !empty($result_array) ? $data->pwdCheck($password,$result_array[0]->password) : false;
    if( $record ){
      $user = $result_array[0];
      $usr = new \StdClass();
      $usr->id = $usr->uniqueid = $user->id;
      $usr->access_group = $user->work_group;
      $usr->access_rank = (
          \is_array($access_ranks) && \array_key_exists($usr->access_group,$access_ranks)
        ) ? $access_ranks[$usr->access_group]
          : 0;
      $usr->name = $user->name;
      $usr->surname = $user->surname;
      $usr->email = $user->email;
      $usr->phone = $user->phone;
      $usr->status = $user->status;
      $usr->country_code = $user->country_code;
      $usr->avatar = (!empty($user->avatar) && Generic::urlExist($user->avatar))
        ? $user->avatar
        : "/app/tymfrontiers-cdn/admin.soswapp/img/default-avatar.png";
      return $usr;
    }
    return false;
  }
  // public function _create() { return false; }
  public function id () { return $this->_id; }
  public function author () { return $this->_author; }
  public function hasAccess (string $path, string $domain) {
    global $database;
    $path_name = $database->escapeValue($path);
    $domain = $database->escapeValue($domain);
    $user = $database->escapeValue($this->_id);
    if (!\in_array($this->status, ["ACTIVE"]) ) return false;
    $query = "SELECT * FROM :db:.path_access
              WHERE (
                path_name = (
                  SELECT name
                  FROM :db:.work_path
                  WHERE domain = '{$domain}'
                  AND `path` = '{$path_name}'
                  LIMIT 1
                )
                AND user = '{$user}'
              )
              OR (
                user = '{$user}'
                AND path_name = (
                    SELECT `name`
                    FROM :db:.`work_path`
                    WHERE `domain` = '{$domain}'
                    AND `path` = '/'
                    LIMIT 1
                )
              )
            LIMIT 1";
    return (bool) self::findBySql($query);
  }
  // check environment
  private static function _checkEnv(){
    global $database;
    if ( !$database instanceof \TymFrontiers\MySQLDatabase ) {
      if(
        !\defined("MYSQL_ADMIN_DB") ||
        !\defined("MYSQL_SERVER") ||
        !\defined("MYSQL_GUEST_USERNAME") ||
        !\defined("MYSQL_GUEST_PASS")
      ){
        throw new \Exception("Required defination(s)[MYSQL_ADMIN_DB, MYSQL_SERVER, MYSQL_GUEST_USERNAME, MYSQL_GUEST_PASS] not [correctly] defined.", 1);
      }
      // check if guest is logged in
      $GLOBALS['database'] = new \TymFrontiers\MySQLDatabase(MYSQL_SERVER, MYSQL_GUEST_USERNAME, MYSQL_GUEST_PASS, self::$_db_name);
    }
  }
}
