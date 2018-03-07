<?php

if (!class_exists('TObjetStd'))
{
	/**
	 * Needed if $form->showLinkedObjectBlock() is call
	 */
	define('INC_FROM_DOLIBARR', true);
	require_once dirname(__FILE__).'/../config.php';
}


class Questionnaire extends SeedObject
{
	/**
	 * Draft status
	 */
	const STATUS_DRAFT = 0;
	/**
	 * Validated status
	 */
	const STATUS_VALIDATED = 1;
	/**
	 * Closed status
	 */
	const STATUS_CLOSED = 2;
	
	public static $TStatus = array(
		self::STATUS_DRAFT => 'Draft'
		,self::STATUS_VALIDATED => 'Validate'
		,self::STATUS_CLOSED=> 'Closed'
	);
	
	public $table_element = 'questionnaire';

	public $element = 'questionnaire';
	
	public function __construct($db)
	{
		global $conf,$langs;
		
		$this->db = $db;
		
		$this->fields=array(
				'ref'=>array('type'=>'string','length'=>50,'index'=>true)
				,'title'=>array('type'=>'string')
				,'description'=>array('type'=>'string')
				,'entity'=>array('type'=>'integer','index'=>true)
				,'fk_status'=>array('type'=>'integer','index'=>true) // date, integer, string, float, array, text
				,'import_key'=>array('type'=>'integer','index'=>true)
				,'type_object_linked'=>array('type'=>'string')
				,'fk_object_linked'=>array('type'=>'integer','index'=>true)
				,'fk_user_author'=>array('type'=>'integer','index'=>true)
		);
		
		$this->init();
		
		$this->status = self::STATUS_DRAFT;
		$this->entity = $conf->entity;
	}

	public function save($addprov=false)
	{
		global $user;
		
		if (!$this->id) $this->fk_user_author = $user->id;
		
		$res = $this->id>0 ? $this->updateCommon($user) : $this->createCommon($user);
		
		if ($addprov || !empty($this->is_clone))
		{
			$this->ref = '(PROV'.$this->id.')';
			
			if (!empty($this->is_clone)) $this->status = self::STATUS_DRAFT;
			
			$wc = $this->withChild;
			$this->withChild = false;
			$res = $this->id>0 ? $this->updateCommon($user) : $this->createCommon($user);
			$this->withChild = $wc;
		}
		
		return $res;
	}
	
	
	public function loadBy($value, $field, $annexe = false)
	{
		$res = parent::loadBy($value, $field, $annexe);
		
		return $res;
	}
	
	public function load($id, $ref=null, $loadChild = true)
	{
		global $db;
		
		$res = parent::fetchCommon($id, $ref);
		
		if ($loadChild) $this->fetchObjectLinked();
		
		return $res;
	}
	
	public function delete()
	{
		global $user;
		
		$this->generic->deleteObjectLinked();
		
		parent::deleteCommon($user);
	}
	
	public function setDraft()
	{
		if ($this->status == self::STATUS_VALIDATED)
		{
			$this->status = self::STATUS_DRAFT;
			$this->withChild = false;
			
			return self::save();
		}
		
		return 0;
	}
	
	public function setValid()
	{
//		global $user;
		
		$this->ref = $this->getNumero();
		$this->status = self::STATUS_VALIDATED;
		
		return self::save();
	}
	
	public function getNumero()
	{
		if (preg_match('/^[\(]?PROV/i', $this->ref) || empty($this->ref))
		{
			return $this->getNextNumero();
		}
		
		return $this->ref;
	}
	
	private function getNextNumero()
	{
		global $db,$conf;
		
		require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
		
		$mask = !empty($conf->global->MYMODULE_REF_MASK) ? $conf->global->MYMODULE_REF_MASK : 'Q{yy}{mm}-{0000}';
		$numero = get_next_value($db, $mask, 'questionnaire', 'ref');
		
		return $numero;
	}
	
	public function getNomUrl($withpicto=0, $get_params='')
	{
		global $langs;

        $result='';
        $label = '<u>' . $langs->trans("Showquestionnaire") . '</u>';
        if (! empty($this->ref)) $label.= '<br><b>'.$langs->trans('Ref').':</b> '.$this->ref;
        
        $linkclose = '" title="'.dol_escape_htmltag($label, 1).'" class="classfortooltip">';
        $link = '<a href="'.dol_buildpath('/questionnaire/card.php', 1).'?id='.$this->id. $get_params .$linkclose;
       
        $linkend='</a>';

        $picto='generic';
		
        if ($withpicto) $result.=($link.img_object($label, $picto, 'class="classfortooltip"').$linkend);
        if ($withpicto && $withpicto != 2) $result.=' ';
		
        $result.=$link.$this->ref.$linkend;
		
        return $result;
	}
	
	public static function getStaticNomUrl($id, $withpicto=0)
	{
		global $db;
		
		$object = new questionnaire($db);
		$object->load($id, '',false);
		
		return $object->getNomUrl($withpicto);
	}
	
	public function getLibStatut($mode=0)
    {
        return self::LibStatut($this->status, $mode);
    }
	
	public static function LibStatut($status, $mode)
	{
		global $langs;
		$langs->load('questionnaire@questionnaire');

		if ($status==self::STATUS_DRAFT) { $statustrans='statut0'; $keytrans='questionnaireStatusDraft'; $shortkeytrans='Draft'; }
		if ($status==self::STATUS_VALIDATED) { $statustrans='statut1'; $keytrans='questionnaireStatusValidated'; $shortkeytrans='Validate'; }
		if ($status==self::STATUS_CLOSED) { $statustrans='statut6'; $keytrans='questionnaireStatusClosed'; $shortkeytrans='Closed'; }

		
		if ($mode == 0) return img_picto($langs->trans($keytrans), $statustrans);
		elseif ($mode == 1) return img_picto($langs->trans($keytrans), $statustrans).' '.$langs->trans($keytrans);
		elseif ($mode == 2) return $langs->trans($keytrans).' '.img_picto($langs->trans($keytrans), $statustrans);
		elseif ($mode == 3) return img_picto($langs->trans($keytrans), $statustrans).' '.$langs->trans($shortkeytrans);
		elseif ($mode == 4) return $langs->trans($shortkeytrans).' '.img_picto($langs->trans($keytrans), $statustrans);
	}
	
	function loadQuestions() {
		
		global $db;
		
		$sql = 'SELECT rowid
				FROM '.MAIN_DB_PREFIX.'question
				WHERE fk_questionnaire = '.$this->id;
		$resql = $db->query($sql);
		if(!empty($resql) && $db->num_rows($resql) > 0) {
			$this->questions = array();
			dol_include_once('/questionnaire/class/question.class.php');
			while($res = $db->fetch_object($resql)) {
				$q = new Question($db);
				$q->load($res->rowid);
				$this->questions[] = $q;
			}
			
		} else return 0;
		
		return 1;
		
	}
	
}


/*
class questionnaireDet extends TObjetStd
{
	public $table_element = 'questionnairedet';

	public $element = 'questionnairedet';
	
	public function __construct($db)
	{
		global $conf,$langs;
		
		$this->db = $db;
		
		$this->init();
		
		$this->user = null;
	}
	
	
}
*/