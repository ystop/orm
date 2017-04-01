<?php
/**
 * Created by PhpStorm.
 * User: yangshuai-s
 * Date: 2017/3/31
 * Time: 11:33
 */
require "DBFactory.php";

class ActiveRecord
{
    /**
     * 数据库连接
     */
    private $__db;

    /**
     * 表名
     */
    protected $_table;

    /**
     * 当前sql语句
     */
    protected $_sql;

    /**
     * sql options
     */
    protected $_options = [];

    /**
     * 属性
     */
    protected $_attribute = [];

    /**
     * 多个实例（不典型）
     * @var array
     */
    protected $_records = [];

    /**
     * ActiveRecord constructor.
     */
    public function __construct()
    {
        $this->__db = DBFactory::getInstance();
    }

    public function where()
    {
        $this->_options['where'][] = func_get_args();
        return $this;
    }

    public function fields()
    {
        $this->_options['fields'] = func_get_args();
        return $this;
    }


    public function get()
    {
        //build opt
        $opt = $this->_buildOpt();
        $sql = "select {$opt['fields']} from {$this->_table} {$opt['where']} {$opt['group_by']} {$opt['having']}";
        $records = $this->run($sql);
        $this->_records = $this->_createModels($records);
        return $this;
    }

    protected function _createModels($rows)
    {
        if (count($rows) == 1) {
            $this->_populate($rows[0]);
            return [];
        }

        $models = [];
        foreach ($rows as $k => $v) {
            $newModel = new static();
            $models[] = $newModel->_populate($v);
        }
        return $models;
    }

    protected function _populate($row)
    {
        //验证
        foreach ($row as $k => $v) {
            $this->_attribute[$k] = $v;
        }
        return $this;
    }

    public function toArray()
    {
        if (empty($this->_records)) {
            return $this->_attribute;
        } else {
            $res = [];
            foreach ($this->_records as $k => $v) {
                $res[] = $v->_attribute;
            }
            return $res;
        }
    }

    public function toJson()
    {
        return json_encode($this->toArray());
    }

    private function _buildOpt()
    {
        $opt = [];
        $opt['where'] = '';
        $opt['fields'] = '*';
        $opt['group_by'] = '';
        $opt['having'] = '';
        //build where
        if (isset($this->_options['where'])) {
            $opt['where'] .= 'where ';
            $lastWhere = array_pop($this->_options['where']);
            foreach ($this->_options['where'] as $k => $v) {
                $opt['where'] = $opt['where'] . implode(' ', $v) . " and ";
            }
            $opt['where'] .= implode(' ', $lastWhere);
        }

        //build fields
        if (isset($this->_options['fields'])) {
            $opt['fields'] = implode(", ", $this->_options['fields']);
        }

        //build group by
        if (isset($this->_options['group_by'])) {
            $opt['group_by'] = " group by ". $this->_options['group_by'] . " ";
        }

        //build having
        if (isset($this->_options['having'])) {
            $opt['having'] = ' having ' . implode(" " ,$this->_options['having']);
        }
        return $opt;
    }

    /**
     * 聚合类
     */
    public function count()
    {
        $this->_options['fields'][] = 'count(*)';
        $count = $this->get();
        return $count->_attribute['count(*)'];
    }

    public function min($field)
    {
        $this->_options['fields'][] = "min($field)";
        $min = $this->get();
        return $min->_attribute["min($field)"];
    }

    public function groupBy($fields)
    {
        $this->_options['group_by'] = $fields;
        return $this;
    }

    public function having()
    {
        $this->_options['having'] = func_get_args();
        return $this;
    }

    public function orderBy($field, $direction = 'asc')
    {
        $this->_options['order_by'][$field] = $direction;
    }

    public function run($sql, $bind = null)
    {
        $this->_sql = $sql;
        $db= $this->__db->prepare($sql);
        $db->execute($bind);
        $info = $db->fetchAll(PDO::FETCH_ASSOC);
        return $info;
    }

}

class User extends ActiveRecord
{
    protected $_table = "user";

    public function getUser1($userId)
    {
        return $this->fields("username","id")->where('id' ,'=', $userId)->where('sex', '>', 0)->get()->toArray();
    }

    public function getUser2($userId)
    {
        return $this->fields("username","id")->where('sex', '>=', 0)->get();
    }

    public function getUser3($userId)
    {
        return $this->fields("username","id")->where('sex', '>=', 0)->min('created_at');
    }

    public function getUser4($userId)
    {
        return $this->fields("username","id")->where('sex', '>=', 0)->groupBy('username')->having('id', '>', 1)->get()->toArray();
    }

}

class UserService
{
    private $__userDao;

    public function __construct()
    {
        $this->__userDao = new User();
    }


    /**
     * 业务
     */
    public function biz()
    {
        $id = 1;
        $userInfo1 = $this->__userDao->getUser1($id);
        $userInfo2 = $this->__userDao->getUser2($id);
        $userInfo3 = $this->__userDao->getUser3($id);
        $userInfo4 = $this->__userDao->getUser4($id);
        die(var_dump($userInfo1, $userInfo2, $userInfo3, $userInfo4));
    }


}

$userService = new UserService();
$userService->biz();