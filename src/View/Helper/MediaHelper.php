<?php

namespace Media\View\Helper;

use Cake\Core\Plugin;
use Cake\Log\Log;
use Cake\Routing\Router;
use Cake\View\Helper;
use Cake\View\Helper\FormHelper;
use Cake\View\Helper\HtmlHelper;
use Cake\View\Helper\UrlHelper;
use Cake\View\View;
use Media\Lib\Image\ImageProcessor;

/**
 * Class MediaHelper
 * @package Media\View\Helper
 * @property HtmlHelper $Html
 * @property UrlHelper $Url
 * @property FormHelper $Form
 */
class MediaHelper extends Helper
{
    public $helpers = ['Html', 'Url', 'Form'];

    /**
     * @var ImageProcessor
     */
    protected $_processor;

    public function __construct(View $View, array $config = [])
    {
        parent::__construct($View, $config);

        // Load ImageProcessor if Imagine is available
        try {

            $processor = new ImageProcessor();
            if ($processor->imagine() !== null) {
                $this->_processor = $processor;
            }

        } catch (\Exception $ex) {
            Log::warning('MediaHelper: ' . $ex->getMessage());
        }


        $widgets = [
            'media_picker' => ['Media\View\Widget\MediaPickerWidget']
        ];
        foreach ($widgets as $type => $config) {
            $this->Form->addWidget($type, $config);
        }

        //@todo remove the dependency on Backend plugin
        //$this->Html->css('Backend.jstree/themes/backend/style.min', ['block' => true]);
        //$this->Html->script('Backend.jstree/jstree.min', ['block' => true]);
        $this->_View->loadHelper('Backend.JsTree');

        $this->Html->script('/backend/libs/underscore/underscore-min', ['block' => 'script']);
        $this->Html->script('/backend/libs/backbone/backbone-min', ['block' => 'script']);

        $this->Html->css('Media.mediapicker', ['block' => true]);
        $this->Html->script('Media.mediapicker', ['block' => 'script']);
    }

    public function thumbnailUrl($source, $options = [], $full = false)
    {
        $path = $this->_generateThumbnail($source, $options);
        if ($full) {
            return $this->Url->build($path, $full);
        }

        return $path;
    }

    public function thumbnail($source, $options = [], $attr = [])
    {
        $source = $this->_generateThumbnail($source, $options);
        return $this->Html->image($source, $attr);
    }

    protected function _generateThumbnail($source, $options = []) {

        if (!$this->_processor) {
            debug("Image processor not loaded");
            return $source;
        }

        if (!file_exists($source) || preg_match('/\:\/\//', $source)) {
            debug("Image not found at " . $source);
            return $source;
        }

        $info = pathinfo($source);

        $thumbBasename = $info['filename'] . '_' . md5(serialize($options)) . '.' . $info['extension'];
        $thumbPath = WWW_ROOT . 'cache/' . $thumbBasename;
        $thumbUri = '/cache/' . $thumbBasename;

        if (file_exists($thumbPath)) {
            return $thumbUri;
        }

        try {

            $this->_processor
                ->open($source)
                ->thumbnail($options)
                ->save($thumbPath);

            return $thumbUri;

        } catch (\Exception $ex) {
            debug($ex->getMessage());
        }

        return $source;


    }
}
