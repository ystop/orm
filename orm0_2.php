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
     * 是否记录时间戳
     */
    protected $_timestamps = true;

    /**
     * 是否软删除
     */
    protected $_softDelete = true;

    /**
     * 单机嵌套事务(解决一条连接不可嵌套)
     */
    protected $_transactions = 0;

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
        $opt['where'] = $this->__buildWhere();
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

    private function __buildWhere()
    {
        $where = '';
        if (isset($this->_options['where'])) {
            $where .= 'where ';
            $lastWhere = array_pop($this->_options['where']);
            foreach ($this->_options['where'] as $k => $v) {
                $where = $where . implode(' ', $v) . " and ";
            }
            $where .= implode(' ', $lastWhere);
        }
        return $where;
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
        $this->get();
        return $this->_attribute["min($field)"];
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

    //插入
    public function __set($k, $v)
    {
        $this->_attribute[$k] = $v;
    }

    public function __get($k)
    {
        return  $this->_attribute[$k] ?  $this->_attribute[$k] : null;
    }

    /**
     * 只支持insert
     * @return mixed
     */
    public function save()
    {
        if ($this->_timestamps) {
            $this->_attribute['created_at'] = time();
            $this->_attribute['updated_at'] = time();
        }

        //build opt
        $attribute = implode(',',array_keys($this->_attribute));
        $bind = ':' . implode(' ,:',array_keys($this->_attribute));
        $sql = "insert into {$this->_table} ({$attribute}) values ( {$bind} )";
        $bind = [];
        foreach ($this->_attribute as $k => $v) {
            $bind[":$k"] = $v;
        }
        $this->_beforeInsert();
        $rs = $this->run($sql, $bind ,1);
        $this->_afterInsert();
        return $rs;
    }

    protected function _beforeInsert()
    {

    }

    protected function _afterInsert()
    {

    }

    public function update($params)
    {
        if ($this->_timestamps) {
            $params['updated_at'] = time();
        }
        $opt = [];
        $opt['where'] = '';
        $opt['where'] = $this->__buildWhere();
        $updateParams = '';
        foreach ($params as $k => $v) {
            $updateParams .= $k . " = :$k ,";
        }
        $updateParams = substr($updateParams, 0 ,strlen($updateParams)-1);
        $sql = "update  {$this->_table} set {$updateParams} {$opt['where']} ";
        $bind = [];
        foreach ($params as $k => $v) {
            $bind[":$k"] = $v;
        }
        $rs = $this->run($sql, $bind ,1);
        return $rs;
    }


    public function run($sql, $bind = null, $way = 0)
    {
        $this->_sql = $sql;
        $db= $this->__db->prepare($sql);
        $rs = $db->execute($bind);
        if ($way == 1) {
            return $rs;
        } else {
            $info = $db->fetchAll(PDO::FETCH_ASSOC);
            return $info;
        }
    }

    public function beginTransaction()
    {
        ++$this->_transactions;
        if ($this->_transactions == 1) {
            $this->__db->beginTransaction();
        }
    }

    public function commit()
    {
        if ($this->_transactions == 1) {
            $this->__db->commit();
        }
        --$this->_transactions;
    }

    public function rollback()
    {
        if ($this->_transactions == 1) {
            $this->__db->rollback();
        }
        --$this->_transactions;
    }

}

class User extends ActiveRecord
{
    protected $_table = "user";

    public function getUser1($userId)
    {
        $userId = 1;
        return $this->fields("username","id")->where('id' ,'=', $userId)->where('sex', '>', 0)->get();
    }

    public function getUser2($userId)
    {
        return $this->fields("username","id")->where('sex', '>=', 0)->get()->toArray();
    }

    public function getUser3($userId)
    {
        return $this->fields("username","id")->where('sex', '>=', 0)->min('id');
    }

    public function getUser4($userId)
    {
        return $this->where('sex', '>=', 0)->groupBy('username')->having('id', '>', 1)->get()->toArray();
    }

    public function addUser()
    {
        $this->beginTransaction();
        $this->beginTransaction();
        $this->username = "ystop2";
        $this->sex = 1;
        $rs = $this->save();
        $this->rollback();
        $this->commit();
        return $rs;

    }

    public function updateUser()
    {
        return $this->where('id','=',1)->update(['sex' => 3]);
    }

}

class UserService
{
    /**
     * 业务
     */
    public function biz()
    {
        $id = 1;
        $userInfo1 = (new User())->getUser1($id);
        $userInfo2 = (new User())->getUser2($id);
        $userInfo3 = (new User())->getUser3($id);
        $userInfo4 = (new User())->getUser4($id);
        $userInfo5 = (new User())->addUser();
        $userInfo6 = (new User())->updateUser();
        die(var_dump($userInfo6));
    }
}

$userService = new UserService();
$userService->biz();