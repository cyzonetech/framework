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

namespace think\model\concern;

use think\Collection;
use think\Exception;
use think\Loader;
use think\Model;
use think\model\Collection as ModelCollection;

/**
 * 模型数据转换处理
 */
trait Conversion
{
    /**
     * 数据输出显示的属性
     * @var array
     */
    protected $_visible = [];

    /**
     * 数据输出隐藏的属性
     * @var array
     */
    protected $_hidden = [];

    /**
     * 数据输出需要追加的属性
     * @var array
     */
    protected $_append = [];

    /**
     * 数据集对象名
     * @var string
     */
    protected $_resultSetType;

    /**
     * 设置需要附加的输出属性
     * @access public
     * @param  array $append   属性列表
     * @param  bool  $override 是否覆盖
     * @return $this
     */
    public function append(array $append = [], $override = false)
    {
        $this->_append = $override ? $append : array_merge($this->_append, $append);

        return $this;
    }

    /**
     * 设置附加关联对象的属性
     * @access public
     * @param  string       $attr    关联属性
     * @param  string|array $append  追加属性名
     * @return $this
     * @throws Exception
     */
    public function appendRelationAttr($attr, $append)
    {
        if (is_string($append)) {
            $append = explode(',', $append);
        }

        $relation = Loader::parseName($attr, 1, false);
        if (isset($this->_relation[$relation])) {
            $model = $this->_relation[$relation];
        } else {
            $model = $this->getRelationData($this->$relation());
        }

        if ($model instanceof Model) {
            foreach ($append as $key => $attr) {
                $key = is_numeric($key) ? $attr : $key;
                if (isset($this->_data[$key])) {
                    throw new Exception('bind attr has exists:' . $key);
                } else {
                    $this->_data[$key] = $model->$attr;
                }
            }
        }

        return $this;
    }

    /**
     * 设置需要隐藏的输出属性
     * @access public
     * @param  array $hidden   属性列表
     * @param  bool  $override 是否覆盖
     * @return $this
     */
    public function hidden(array $hidden = [], $override = false)
    {
        $this->_hidden = $override ? $hidden : array_merge($this->_hidden, $hidden);

        return $this;
    }

    /**
     * 设置需要输出的属性
     * @access public
     * @param  array $visible
     * @param  bool  $override 是否覆盖
     * @return $this
     */
    public function visible(array $visible = [], $override = false)
    {
        $this->_visible = $override ? $visible : array_merge($this->_visible, $visible);

        return $this;
    }

    /**
     * 转换当前模型对象为数组
     * @access public
     * @return array
     */
    public function toArray()
    {
        $item       = [];
        $hasVisible = false;

        foreach ($this->_visible as $key => $val) {
            if (is_string($val)) {
                if (strpos($val, '.')) {
                    list($relation, $name)      = explode('.', $val);
                    $this->_visible[$relation][] = $name;
                } else {
                    $this->_visible[$val] = true;
                    $hasVisible          = true;
                }
                unset($this->_visible[$key]);
            }
        }

        foreach ($this->_hidden as $key => $val) {
            if (is_string($val)) {
                if (strpos($val, '.')) {
                    list($relation, $name)     = explode('.', $val);
                    $this->_hidden[$relation][] = $name;
                } else {
                    $this->_hidden[$val] = true;
                }
                unset($this->_hidden[$key]);
            }
        }

        // 合并关联数据
        $data = array_merge($this->_data, $this->_relation);

        foreach ($data as $key => $val) {
            if ($val instanceof Model || $val instanceof ModelCollection) {
                // 关联模型对象
                if (isset($this->_visible[$key]) && is_array($this->_visible[$key])) {
                    $val->visible($this->_visible[$key]);
                } elseif (isset($this->_hidden[$key]) && is_array($this->_hidden[$key])) {
                    $val->hidden($this->_hidden[$key]);
                }
                // 关联模型对象
                if (!isset($this->_hidden[$key]) || true !== $this->_hidden[$key]) {
                    $item[$key] = $val->toArray();
                }
            } elseif (isset($this->_visible[$key])) {
                $item[$key] = $this->getAttr($key);
            } elseif (!isset($this->_hidden[$key]) && !$hasVisible) {
                $item[$key] = $this->getAttr($key);
            }
        }

        // 追加属性（必须定义获取器）
        if (!empty($this->_append)) {
            foreach ($this->_append as $key => $name) {
                if (is_array($name)) {
                    // 追加关联对象属性
                    $relation = $this->getRelation($key);

                    if (!$relation) {
                        $relation = $this->getAttr($key);
                        if ($relation) {
                            $relation->visible($name);
                        }
                    }

                    $item[$key] = $relation ? $relation->append($name)->toArray() : [];
                } elseif (strpos($name, '.')) {
                    list($key, $attr) = explode('.', $name);
                    // 追加关联对象属性
                    $relation = $this->getRelation($key);

                    if (!$relation) {
                        $relation = $this->getAttr($key);
                        if ($relation) {
                            $relation->visible([$attr]);
                        }
                    }

                    $item[$key] = $relation ? $relation->append([$attr])->toArray() : [];
                } else {
                    $item[$name] = $this->getAttr($name, $item);
                }
            }
        }

        return $item;
    }

    /**
     * 转换当前模型对象为JSON字符串
     * @access public
     * @param  integer $options json参数
     * @return string
     */
    public function toJson($options = JSON_UNESCAPED_UNICODE)
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * 移除当前模型的关联属性
     * @access public
     * @return $this
     */
    public function removeRelation()
    {
        $this->_relation = [];
        return $this;
    }

    public function __toString()
    {
        return $this->toJson();
    }

    // JsonSerializable
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * 转换数据集为数据集对象
     * @access public
     * @param  array|Collection $collection 数据集
     * @param  string           $resultSetType 数据集类
     * @return Collection
     */
    public function toCollection($collection, $resultSetType = null)
    {
        $resultSetType = $resultSetType ?: $this->_resultSetType;

        if ($resultSetType && false !== strpos($resultSetType, '\\')) {
            $collection = new $resultSetType($collection);
        } else {
            $collection = new ModelCollection($collection);
        }

        return $collection;
    }

}
