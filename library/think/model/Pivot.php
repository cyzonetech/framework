<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace think\model;

use think\Model;

class Pivot extends Model
{

    /** @var Model */
    public $_parent;

    protected $autoWriteTimestamp = false;

    /**
     * 架构函数
     * @access public
     * @param  array|object  $data 数据
     * @param  Model         $parent 上级模型
     * @param  string        $table 中间数据表名
     */
    public function __construct($data = [], Model $parent = null, $table = '')
    {
        $this->_parent = $parent;

        if (is_null($this->_name)) {
            $this->_name = $table;
        }

        parent::__construct($data);
    }

}
