<?php
class WPicturePost extends UFormWorklet
{
	public $modelClassName = 'MPictureForm';
	public $post;
	
	public function accessRules()
	{
		return array(
			array('deny', 'users'=>array('?'))
		);
	}
	
	public function afterModel()
	{
		if($this->isPopup())
		{
			if(isset($_GET['image']))
				$this->model->imageUrl = $_GET['image'];
			if(isset($_GET['source']))
				$this->model->source = $_GET['source'];
			
			app()->controller->layout = 'popup';
		}
	}
	
	public function taskIsPopup()
	{
		return app()->controller->routeEased == 'picture/post' && (!isset($_POST['isPopup']) || $_POST['isPopup']);
	}
	
	public function properties()
	{
		return array(
			'action' => url('/picture/post'),
			'elements' => array(
				CHtml::hiddenField('isPopup', $this->isPopup()),
				'parentId' => array('type' => 'hidden'),
				'imageUrl' => array('type' => 'hidden'),
				'source' => array('type' => 'hidden'),
				'boardId' => array('type' => 'dropdownlist', 'items' => wm()->get('picture.helper')->boards()),
				'message' => array('type' => 'textarea', 'layout' => "<div class=\"clearfix\">{label}</div>{input}\n{hint}"),
			),
			'buttons' => array(
				'submit' => array('type' => 'submit',
					'label' => $this->t('{#post_v_ucf} It'))
			),
			'model' => $this->model
		);
	}
	
	public function beforeRenderOutput()
	{
		cs()->registerScriptFile(asma()->publish($this->module->basePath.DS.'js'.DS.'simpleSlide'.DS.'jquery.simpleSlide.js'));
	}
	
	public function taskSettings()
	{
		return array(
			'noImagesFound' => str_replace('"','\"',$this->t('Unfortunately we were not able to find any big images.')),
			'minWidth' => $this->param('minWidth'),
		);
	}
	
	public function afterRenderOutput()
	{
		$settings = CJavaScript::encode($this->settings());
		cs()->registerScript(__CLASS__,'$.uniprogy.picture.post.init('.$settings.');');
		if($this->isPopup())
		{
			$images = CJavaScript::jsonEncode(array($this->model->imageUrl));
			cs()->registerScript('$.uniprogy.picture.post.load',
				'$.uniprogy.picture.post.load('. $images.',"'.CJavaScript::quote($this->model->source).'")');
		}
	}
	
	public function taskSave()
	{
		$helper = wm()->get('picture.helper');
		$channel = '';
		$picture = null;
		
		if($this->model->parentId)
		{
			$parent = MPicturePost::model()->findByPk($this->model->parentId);
			$this->post = $helper->repost($parent, $this->model->boardId, $this->model->message);
			
			wm()->get('picture.event')->repost($parent->id,$this->post->id);			
			wm()->get('picture.helper')->updateStats($this->model->parentId, 'reposts');
			
			$this->successUrl = url('/picture/view', array('id' => $this->post->id));
			return true;
		}
		elseif(is_numeric($this->model->source))
		{
			$bin = app()->storage->bin($this->model->source);
			if($bin)
			{
				$picture = $helper->savePicture($bin->getFilePath('original'), $bin);
				$channel = 'upload';
				$this->model->source = null;
			}
		}
		elseif($this->model->imageUrl)
		{
			$file = $helper->saveInStorage($this->model->imageUrl);
			if($file)
			{
				$picture = $helper->savePicture($file);
				$channel = 'web';
			}
		}
		
		if($picture)
		{
			$this->post = $helper->post($picture->id, $this->model->boardId, $this->model->message, $channel, $this->model->source);
			wm()->get('picture.event')->post($this->post->id);
			$this->successUrl = url('/picture/view', array('id' => $this->post->id));
			return true;
		}
		$this->model->addError('boardId', $this->t('Unknown error occured. Please try again later.'));
	}
	
	public function ajaxSuccess()
	{
		if($this->isPopup())
		{
			$content = $this->render('thankYou',array(),true);
			wm()->get('base.init')->addToJson(array(
				'content' => array('replace' => 
					$content
					.CHtml::script('jQuery("#closeButton").click(function(){window.close();});')
					.CHtml::script('jQuery("#viewButton").click(function(){window.open("'.aUrl('/picture/view', array('id' => $this->post->id)).'");});')
				),
			));
		}
		else
			parent::ajaxSuccess();
	}
}