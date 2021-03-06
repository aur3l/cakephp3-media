<?php
namespace Media\Controller\Admin;


use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use Media\Lib\Media\MediaManager;

class MediaManagerController extends AppController
{

    public function treeData()
    {
        $this->viewBuilder()->className('Json');

        $id = $this->request->query('id');
        $path = ($id == '#') ? '/' : $id;
        $treeData = [];
        $config = $this->request->query('config');

        $mm = MediaManager::get($config);
        $mm->open($path);

        $folders = $mm->listFoldersRecursive(0);
        array_walk($folders, function ($val) use (&$treeData, &$id) {
            $treeData[] = [
                'id' => $val,
                'text' => basename($val),
                'children' => true,
                'type' => 'folder',
                'parent' => $id
            ];
        });

        /*
        $files = $mm->listFiles();
        array_walk($files, function ($val) use (&$treeData, &$mm, &$parent) {
            $treeData[] = ['id' => $val, 'text' => basename($val), 'children' => false, 'type' => 'file', 'data-icon' => $mm->getFileUrl($val)];
        });
        */


        $this->set('treeData', $treeData);
        $this->set('_serialize', 'treeData');
    }


    public function filesData()
    {
        $this->viewBuilder()->className('Json');

        $id = $this->request->query('id');
        $path = ($id == '#') ? '/' : $id;
        $treeData = [];

        $config = $this->request->query('config');

        $mm = MediaManager::get($config);
        $mm->open($path);

        $files = $mm->listFiles();
        array_walk($files, function ($val) use (&$treeData, &$mm, &$parent) {

            $icon = true;
            $filename = basename($val);
            if (preg_match('/^(.*)\.(jpg|gif|jpeg|png)$/i', $filename)) {
                // use thumbnail as icon
                $icon = $mm->getFileUrl($val);
            } elseif (preg_match('/^\./', $filename)) {
                // ignore dot-files
                return;
            }

            $treeData[] = [
                'id' => $val,
                'text' => basename($val),
                'children' => false,
                'type' => 'file',
                'icon' => $icon
            ];
        });


        $this->set('treeData', $treeData);
        $this->set('_serialize', 'treeData');
    }

    public function setImage()
    {
        $scope = $this->request->query('scope');
        $multiple = $this->request->query('multiple');
        $model = $this->request->query('model');
        $id = $this->request->query('id');
        $config = $this->request->query('config');

        $Table = TableRegistry::get($model);
        
        $Table->behaviors()->unload('Media');
        $content = $Table->get($id, [
            'contain' => [],
            'media' => true,
        ]);

        $file = $content->get($scope);


        if ($this->request->is(['patch', 'post', 'put'])) {
            $patchFile = $this->request->data($scope);
            debug($file);
            debug($patchFile);
            if (is_array($patchFile)) {
                $patchFile = $patchFile[0];
            }
            if (is_array($file)) {
                //$files = explode(',', $file);
                $file[] = $patchFile;
                $patchFile = join(',', $file);
            }
            debug($patchFile);

            $content = $Table->patchEntity($content, [$scope => $patchFile]);
            //$content->$scope = $this->request->data[$scope];
            if ($Table->save($content)) {
                $this->Flash->success(__d('banana','The {0} has been saved.', __d('banana','content')));
            } else {
                $this->Flash->error(__d('banana','The {0} could not be saved. Please, try again.', __d('banana','content')));
            }
        } else {
        }

        $mm = MediaManager::get($config);
        $files = $mm->getSelectListRecursiveGrouped();
        $this->set('imageFiles', $files);
        $this->set('scope', $scope);
        $this->set('multiple', $multiple);
        $this->set('model', $model);
        $this->set('id', $id);
        $this->set('config', $config);

        $this->set(compact('content'));
        $this->set('_serialize', ['content']);
    }

    public function imageSelect()
    {

    }


    public function deleteImage()
    {
        $scope = $this->request->query('scope');
        $multiple = $this->request->query('multiple');
        $model = $this->request->query('model');
        $id = $this->request->query('id');
        $pathEncoded = $this->request->query('img');
        $pathDecoded = base64_decode($pathEncoded);
        $referer = ($this->request->query('ref')) ?: $this->referer();

        $Table = TableRegistry::get($model);

        $Table->behaviors()->unload('Media');
        $content = $Table->get($id, [
            'contain' => [],
            'media' => true,
        ]);

        //if (!in_array($scope, ['teaser_image_file', 'image_file', 'image_files'])) {
        //    throw new BadRequestException('Invalid scope');
        //}

        $updated = '';
        if ($multiple) {
            $file = $content->get($scope);
            if (is_array($file)) {
                $filtered = array_filter($file, function($filepath) use ($pathEncoded) {
                    //Log::debug('Filter ' . $filepath . '[' . base64_encode($filepath) . '] => ' . $pathEncoded);
                    if (base64_encode($filepath) == $pathEncoded) {
                        return false;
                    }
                   return true;
                });
            }
            $updated = join(',', $filtered);
        }

        $content->accessible($scope, true);
        $content->set($scope, $updated);

        if ($Table->save($content)) {
            $this->Flash->success(__d('banana','The {0} has been removed.', $scope));
        } else {
            $this->Flash->error(__d('banana','The {0} could not be removed. Please, try again.', $scope));
        }
        return $this->redirect($referer);
    }
    
}