<?php
namespace Media\Model\Behavior;

use Cake\Collection\Iterator\MapReduce;
use Cake\Event\Event;
use Cake\ORM\Query;

class MediaBehavior extends \Cake\ORM\Behavior
{

    protected $_defaultConfig = [
        // List of observable fields
        'fields' => [],
    ];

    protected $_defaultFieldConfig = [
        // Media config name
        'config' => 'default',
        // Entity class location
        'entityClass' => '\\Media\\Model\\Entity\\MediaFile',
        // Multiple
        'multiple' => false
    ];


    /**
     * @var array List of configured attachment fields
     */
    protected $_fields = [];

    /**
     * @param array $config
     */
    public function initialize(array $config)
    {
        foreach ($this->_config['fields'] as $field => $_config) {
            if (is_numeric($field)) {
                $field = $_config;
                $_config = [];
            }
            $_config = array_merge($this->_defaultFieldConfig, $_config);
            $this->_fields[$field] = $_config;
        }
    }

    /**
     * 'beforeFind' callback
     *
     * Applies a MapReduce to the query, which resolves attachment info
     * if an attachment field is present in the query results.
     *
     * @param Event $event
     * @param Query $query
     * @param ArrayObject $options
     * @param $primary
     */
    public function beforeFind(Event $event, Query $query, \ArrayObject $options, $primary)
    {
        //debug("beforeFind");
        $mapper = function ($row, $key, MapReduce $mapReduce) {

            foreach ($this->_fields as $fieldName => $field) {
                if (isset($row[$fieldName]) && !empty($row[$fieldName])) {
                    $row[$fieldName] = $this->_resolveFile($row[$fieldName], $field);
                }
            }

            $mapReduce->emitIntermediate($row, $key);
        };

        $reducer = function ($bucket, $name, MapReduce $mapReduce) {
            $mapReduce->emit($bucket[0], $name);
        };

        $query->mapReduce($mapper, $reducer);
    }


    /**
     * @param string $filePath Relative file path to configured dataDir
     * @param array $field Field config
     * @return array|MediaFile
     */
    protected function _resolveFile($filePath, $field)
    {
        //debug("resolve media file " . $this->_table->alias() . ":" . $filePath);
        $config =& $this->_config;

        $resolver = function ($filePath) use ($field, $config) {

            $file = new $field['entityClass']();
            $file->config = $field['config'];
            $file->path = $filePath;

            return $file;
        };


        if ($field['multiple']) {
            $paths = explode(',', $filePath);
            $files = [];
            foreach ($paths as $_filePath) {
                $files[] = $resolver($_filePath);
            }
            return $files;

        } else {
            return $resolver($filePath);
        }

    }
}