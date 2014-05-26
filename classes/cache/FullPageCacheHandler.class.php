<?php
/**--====
 * @file: FullPageCacheHandler.class.php
 * @brief: Hook class for implement xpressengine full page cache
 * @author: YoungJoo.Kim <http://superlinuxer.com>
 * @version: $Revision:
 * @date: 20140301
 ====--**/
class FullPageCacheHandler
{
	/*-- FullPageCacheHandler run flag(true|false) --*/
	private $_full_page_cache = __XE_HOOK_FPC__;

	/*--
	 * @brief: Action's cache expiration time
	 * @value:
	 *         0 =:  no cache
	 *         1 <:  cache time(second)
	 --*/
	private $_index_page_cache = __XE_HOOK_FPC_INDEX_EXPIRES__;       /* index's cache expires time(sec) */
	private $_list_page_cache = __XE_HOOK_FPC_LIST_EXPIRES__;         /* list's cache expires time(sec) */
	private $_document_page_cache =__XE_HOOK_FPC_DOCUMENT_EXPIRES__;  /* document's cache expires time(sec) */
	private $_comment_page_cache = __XE_HOOK_FPC_COMMENT_EXPIRES__;   /* comment's cache expires time(sec) */
	private $_search_page_cache = __XE_HOOK_FPC_SEARCH_EXPIRES__;     /* searched result's cache expires time(sec) */
	private $_menu_page_cache = __XE_HOOK_FPC_MENU_EXPIRES__;         /* menu's cache expires time(sec) */

	/*-- set other than default administrator(admin is not cache) --*/
	private $_admins = array();

	/*-- hits increase flag(true|false) --*/
	private $_update_hits = __XE_HOOK_FPC_UPDATE_HITS__;

	/*-- logged cache flag(true|false) --*/
	private $_logged_full_page_cache = __XE_HOOK_FPC_LOGGED__;

	/*-- hooks() function run flag(true|false) --*/
	private $_run_hook = true;

	/*-- common global variables --*/
	public $oCacheHandler = null;
	public $cfgs = null;
	public $handler = null;

	function __construct()
	{
		/*-- hooking function --*/
		$this->hooks();
		
		/*-- common variables --*/
		$this->oCacheHandler = &CacheHandler::getInstance('object');
		$this->cfgs = new stdClass();
		$this->cfgs->act = Context::get('act');
		$this->cfgs->mid = Context::get('mid');
		$this->cfgs->page = Context::get('page');
		$this->cfgs->category = Context::get('category');
		$this->cfgs->rnd = Context::get('rnd');
		$this->cfgs->cpage = Context::get('cpage');
		$this->cfgs->document_srl = Context::get('document_srl');
		$this->cfgs->menu_srl = Context::get('menu_srl');
		$this->cfgs->request_uri = $_SERVER['REQUEST_URI'];
		$this->cfgs->request_method = Context::getRequestMethod();

		/*-- search variables(for my sphinx) --*/
		$this->cfgs->sfp = Context::get('sfp');
		$this->cfgs->is_keyword = Context::get('is_keyword');
		$this->cfgs->target = Context::get('target');
		$this->cfgs->where = Context::get('where');
		$this->cfgs->search_target = Context::get('search_target');
		$this->cfgs->target_module_srl = Context::get('target_module_srl');

		/*-- devices(pc|mobile) --*/
		$this->cfgs->device = Mobile::isFromMobilePhone() ? 'mobile' : 'pc';

		/*-- login status --*/
		$this->cfgs->is_logged = self::isLogged();

		/*-- cache expire set --*/
		$this->cfgs->expire_index = $this->_index_page_cache;
		$this->cfgs->expire_page = $this->_list_page_cache;
		$this->cfgs->expire_document = $this->_document_page_cache;
		$this->cfgs->expire_comment = $this->_comment_page_cache;
		$this->cfgs->expire_search = $this->_search_page_cache;
		$this->cfgs->expire_menu = $this->_menu_page_cache;

		/*-- cache commands variable --*/
		$this->cfgs->cache_cmd;

		/*-- display debug infomations(cache_key) --*/
		$this->cfgs->debug_info = true;
	}

	/*--==== 
	 * @brief: Hook function to reset variables or actions
	 ====--*/
	public function hooks()
	{
		if (!$this->_run_hook) return false;

		/*-- hook: board search to sphinx --*/
		if (Context::get('search_keyword'))
		{
			Context::set('act', 'IS');
			Context::set('sfp', 'board');
			Context::set('is_keyword', Context::get('search_keyword'));
		}
	}

	/*--====
	 * @brief: FullPageCacheHandler constructor
	 ====--*/
	public function &getInstance()
	{
		$cache_handler_key = 'object';
		if(!$GLOBALS['__XE_FULL_PAGE_HANDLER__'][$cache_handler_key])
		{
			$GLOBALS['__XE_FULL_PAGE_HANDLER__'][$cache_handler_key] = new FullPageCacheHandler();
		}
		return $GLOBALS['__XE_FULL_PAGE_HANDLER__'][$cache_handler_key];
	}

	/*--====
	 * @brief: Main function
	 ====--*/
	public function init($fullPageBuffer = null)
	{
		if (!$this->_full_page_cache) return false;

		$this->cfgs->cache_cmd = $fullPageBuffer ? 'PUT' : 'GET';

		$this->initCache();

		if ($this->isAdmin()) return false;

		if (!$this->_logged_full_page_cache && $this->cfgs->is_logged) return false;

		if (!$this->isSupport()) return false;
		
		$cacheVars = call_user_func_array(array(&$this, $this->cfgs->cur_act), array($fullPageBuffer));
		if (!$cacheVars) return false;

		return $cacheVars;
	}

	/*--====
	 * @brief: Global replacement for dynamic page
	 ====--*/
	public function replaceGlobal(&$fullPageBuffer)
	{
		if (!$fullPageBuffer) return false;

		if ($this->cfgs->is_logged && !$this->isMobile())
		{
			$logged_info = $this->getLoggedInfo();
			if ($this->isCachePut())
			{
				$patterns = array('/<!--fpc:logged_info:s-->.*<!--fpc:logged_info:e-->/');
				$replaces = array("{{fpc:logged_info:nick_name}}");
				$fullPageBuffer = preg_replace($patterns, $replaces, $fullPageBuffer);
			}
			else
			{
				$patterns = array('/{{fpc:logged_info:nick_name}}/');
				$replaces = array($logged_info->nick_name);
				$fullPageBuffer = preg_replace($patterns, $replaces, $fullPageBuffer);
			}
		}
		if ($this->cfgs->debug_info)
		{
			if ($this->isCachePut())
			{
				$patterns = array('/<!--fpc:debug_info:s-->.*<!--fpc:debug_info:e-->/');
				$replaces = array("{{fpc:debug_info}}");
				$fullPageBuffer = preg_replace($patterns, $replaces, $fullPageBuffer);
			}
			else
			{
				$patterns = array('/{{fpc:debug_info}}/');
				$replaces = array('<!--debug_info{' . $this->getKey() . '}-->');
				$fullPageBuffer = preg_replace($patterns, $replaces, $fullPageBuffer);
			}
		}
	}

	/*--==== 
	 * @brief: Index's cache set(put|get)
	 ====--*/
	public function ProcCacheIndex($fullPageBuffer)
	{
		if ($fullPageBuffer)
		{
			$this->replaceGlobal($fullPageBuffer);
			$cacheVars = $this->put($this->getKey(), $fullPageBuffer, $this->cfgs->expire_index);
		}
		else
		{
			$cacheVars = $this->get($this->getKey());
			$this->replaceGlobal($cacheVars);
		}
		return $cacheVars;
	}

	/*--==== 
	 * @brief: List's cache set(put|get)
	 ====--*/
	public function ProcCachePage($fullPageBuffer)
	{
		if ($fullPageBuffer)
		{
			$this->replaceGlobal($fullPageBuffer);
			$cacheVars = $this->put($this->getKey(), $fullPageBuffer,  $this->cfgs->expire_page);
		}
		else
		{
			$cacheVars = $this->get($this->getKey('init:page'));
			$cacheVars = $this->get($this->getKey(), $cacheVars);
			$this->replaceGlobal($cacheVars);
		}
		return $cacheVars;
	}
	
	/*--==== 
	 * @brief: Document's cache set(put|get)
	 ====--*/
	public function ProcCacheDocument($fullPageBuffer)
	{
		if ($fullPageBuffer)
		{
			$this->replaceGlobal($fullPageBuffer);
			$patterns = array('/<!--fpc:readed_count:s-->.*<!--fpc:readed_count:e-->/');
			$replaces = array("{{fpc:readed_count}}");
			$fullPageBuffer = preg_replace($patterns, $replaces, $fullPageBuffer);
			$cacheVars = $this->put($this->getKey(), $fullPageBuffer,  $this->cfgs->expire_document);
		}
		else
		{
			$cacheVars = $this->get($this->getKey());
			$readed_count = $this->countUpdate($this->cfgs->document_srl);
			$patterns = array('/{{fpc:readed_count}}/');
			$replaces = array("<span class=\"read\">Views:$readed_count</span>");
			$cacheVars = preg_replace($patterns, $replaces, $cacheVars);
			$this->replaceGlobal($cacheVars);
		}
		return $cacheVars;
	}

	/*--==== 
	 * @brief: comment's cache set(put|get)
	 ====--*/
	public function ProcCacheComment($fullPageBuffer)
	{
		if ($fullPageBuffer)
		{
			$cacheVars = $this->put($this->getKey(), $fullPageBuffer,  $this->cfgs->expire_comment);
		}
		else
		{
			$cacheVars = $this->get($this->getKey());
		}
		return $cacheVars;
	}

	/*--==== 
	 * @brief: Searched result's cache set(put|get)
	 ====--*/
	public function ProcCacheSearch($fullPageBuffer)
	{
		if ($fullPageBuffer)
		{
			$this->replaceGlobal($fullPageBuffer);
			$cacheVars = $this->put($this->getKey(), $fullPageBuffer, $this->cfgs->expire_search);
		}
		else
		{
			$cacheVars = $this->get($this->getKey());
			$this->replaceGlobal($cacheVars);
		}
		return $cacheVars;
	}

	/*--==== 
	 * @brief: Menu's cache set(put|get)
	 ====--*/
	public function ProcCacheMenu($fullPageBuffer)
	{
		if ($fullPageBuffer)
		{
			$this->replaceGlobal($fullPageBuffer);
			$cacheVars = $this->put($this->getKey(), $fullPageBuffer, $this->cfgs->expire_menu);
		}
		else
		{
			$cacheVars = $this->get($this->getKey());
			$this->replaceGlobal($cacheVars);
		}
		return $cacheVars;
	}

	/*--==== 
	 * @brief: Delete document's cache
	 ====--*/
	public function deleteCacheDocument($cmax = null)
	{
		$ocpage = $this->cfgs->cpage;
		$c = $ocpage + 1; /* max delete cpage: if 5 is 5..0 */
		while($c--)
		{
			$this->cfgs->cpage = $c;
			$cacheVars = $this->delete($this->getKey('ProcCacheDocument', 'pc', 'login'));
			$cacheVars = $this->delete($this->getKey('ProcCacheDocument', 'pc', 'logged'));
			$cacheVars = $this->delete($this->getKey('ProcCacheDocument', 'mobile', 'login'));
			$cacheVars = $this->delete($this->getKey('ProcCacheDocument', 'mobile', 'logged'));
		}
		$this->cfgs->cpage = $ocpage;
		return $cacheVars;
	}

	/*--==== 
	 * @brief: Delete comment's cache
	 ====--*/
	public function deleteCacheComment()
	{
		/*-- Default document's comment cpage is 1(Mobile cpage is 0) --*/
		$ocpage = $this->cfgs->cpage;
		$c = $ocpage + 2; /* max delete cpage: if 5 is 5..0 */
		while($c--)
		{
			$this->cfgs->cpage = $c;
			$cacheVars = $this->delete($this->getKey('ProcCacheComment', 'pc', 'login'));
			$cacheVars = $this->delete($this->getKey('ProcCacheComment', 'pc', 'logged'));
			$cacheVars = $this->delete($this->getKey('ProcCacheComment', 'mobile', 'login'));
			$cacheVars = $this->delete($this->getKey('ProcCacheComment', 'mobile', 'logged'));
		}
		$this->cfgs->cpage = $ocpage;
		return $cacheVars;
	}

	/*--==== 
	 * @brief: Delete list's cache
	 ====--*/
	public function deleteCachePage()
	{
		$cacheVars = $this->delete($this->getKey('ProcCachePage', 'pc', 'login'));
		$cacheVars = $this->delete($this->getKey('ProcCachePage', 'pc', 'logged'));
		$cacheVars = $this->delete($this->getKey('ProcCachePage', 'mobile', 'login'));
		$cacheVars = $this->delete($this->getKey('ProcCachePage', 'mobile', 'logged'));
		return $cacheVars;
	}

	/*--==== 
	 * @brief: Initialize cache
	 ====--*/
	public function initCache()
	{
		if ( !($this->cfgs->request_method == 'XMLRPC') )
		{
			return false;
		}

		/*-- (document|comment) (modify|delete): same xpressengine internal call name --*/
		switch($this->cfgs->act)
		{
			case 'procBoardInsertDocument':
				$cacheVars = $this->put($this->getKey('init:page'), time());
				$cacheVars = $this->deleteCacheDocument();
				break;
			case 'procBoardDeleteDocument':
				$cacheVars = $this->deleteCacheDocument();
				$cacheVars = $this->deleteCacheComment();
				$cacheVars = $this->deleteCachePage();
				break;
			case 'procBoardInsertComment':
			case 'procBoardDeleteComment':
				$cacheVars = $this->deleteCacheDocument();
				$cacheVars = $this->deleteCacheComment();
				break;
			case 'procDocumentVoteUp':
			case 'procDocumentVoteDown':
				/*-- Vote count does not apply directly, because it is the object cache is enabled. --*/
				$odocument_srl = $this->cfgs->document_srl;
				$this->cfgs->document_srl = Context::get('target_srl');

				/*-- Vote function is not transfer cpage variables(You need to implement:) --*/
				$cacheVars = $this->deleteCacheDocument();
				$cacheVars = $this->deleteCacheComment();
				$this->cfgs->document_srl = $oducument_srl;
				break;
			default:
				$cacheVars = false;
		}
		return $cacheVars;
	}

	/*--==== 
	 * @brief: Xe's current action
	 ====--*/
	public function currentAct()
	{
		$curAct = false;
		if ($this->isIndex())
		{
			$curAct = 'ProcCacheIndex';
		}
		else if ($this->isPage())
		{
			$curAct = 'ProcCachePage';
		}
		else if ($this->isDocument())
		{
			$curAct = 'ProcCacheDocument';
		}
		else if ($this->isComment())
		{
			$curAct = 'ProcCacheComment';
		}
		else if ($this->isSearch())
		{
			$curAct = 'ProcCacheSearch';
		}
		else if ($this->isMenu())
		{
			$curAct = 'ProcCacheMenu';
		}
		return $curAct;
	}

	/*--====
	 * @brief: Cache key get
	 * 
	 * @cases:
	 *        1. device(mobile|pc)
	 *        2. login(login|logged)
	 ====--*/
	public function getKey($act = null, $device = null, $isLogged = null)
	{
		/*-- fpc:full page cache --*/
		$keyPrefix = 'fpc';
		$device = $device ? $device : $this->cfgs->device;
		$act = $act ? $act : $this->cfgs->cur_act;
		if (!$isLogged)
		{
			$isLogged = $this->cfgs->is_logged ? 'logged' : 'login';
		}
		switch($act)
		{
			case 'ProcCacheIndex':
				$cacheKey = $keyPrefix . ':' . $device . ':' .  $act . ':' . $isLogged;
				break;
			case 'ProcCachePage':
				/*-- Sort function is disabled by default(You need to implement:) --*/
				$this->cfgs->page = $this->cfgs->page ? $this->cfgs->page : 1;
				$cacheKey = $keyPrefix . ':' . $device . ':' .  $act . ':' . $this->cfgs->mid . ':' . $this->cfgs->page . ':' . $this->cfgs->category . ':' . $isLogged;
				break;
			case 'ProcCacheDocument':
				$this->cfgs->cpage = $this->cfgs->cpage ? $this->cfgs->cpage : '0';
				$cacheKey = $keyPrefix . ':' . $device . ':' .  $act . ':' . $this->cfgs->mid . ':' . $this->cfgs->document_srl . ':' . $this->cfgs->cpage . ':' . $isLogged;
				break;
			case 'ProcCacheComment':
				$this->cfgs->cpage = $this->cfgs->cpage ? $this->cfgs->cpage : '0';
				$cacheKey = $keyPrefix . ':' . $device . ':' .  $act . ':' . $this->cfgs->mid . ':' . $this->cfgs->document_srl . ':' . $this->cfgs->cpage . ':' . $isLogged;
				break;
			case 'ProcCacheSearch':
				$cacheKey = $keyPrefix . ':' . $device . ':' .  $act . ':' ;
				$cacheKey .= $this->cfgs->is_keyword . ':' . $this->cfgs->sfp . ':' . $this->cfgs->target_module_srl . ':';
			  	$cacheKey .= $this->cfgs->search_target . ':' . $this->cfgs->where . ':' . $this->cfgs->target . ':' . $this->cfgs->page . ':' . $isLogged;
				break;
			case 'ProcCacheMenu':
				$cacheKey = $keyPrefix . ':' . $device . ':' .  $act . ':';
				$cacheKey .= $this->cfgs->menu_srl . ':' . $isLogged;
				break;
			case 'init:page':
				$cacheKey = $keyPrefix . ':' . $act . ':' . $this->cfgs->mid;
				break;
			case 'init:document':
				$cacheKey = $keyPrefix . ':' . $act . ':' . $this->cfgs->mid;
				break;
			default:
				$cacheKey = false;
		}
		return $cacheKey;
	}

	/*--====
	 * @brief: Check if xe action is index
	 ====--*/
	public function isIndex()
	{
		$indexs = array('/', '/index.php', '/home');
		if ($this->cfgs->request_method == 'GET' && in_array($this->cfgs->request_uri, $indexs)) return true;
		return false;
	}

	/*--====
	 * @brief: Check if xe action is list
	 ====--*/
	public function isPage()
	{
		if (Context::get('search_keyword') || $this->cfgs->document_srl) return false;
		if ($this->cfgs->mid && $this->cfgs->page && (!$this->cfgs->act || $this->cfgs->act == 'dispBoardContent'))
		{
			return true;
		}
		else if ($this->cfgs->mid && !$this->cfgs->page && !$this->cfgs->act && !$this->cfgs->cpage && !$this->cfgs->rnd)
		{
			return true;
		}
		return false;
	}
 
	/*--====
	 * @brief: Check if xe action is document
	 ====--*/
	public function isDocument()
	{
		if ($this->cfgs->mid && $this->cfgs->document_srl && (!$this->cfgs->act || $this->cfgs->act == 'dispBoardContent')) return true;
		return false;
	}

	/*--====
	 * @brief: Check if xe action is comment
	 ====--*/
	public function isComment()
	{
		if ($this->cfgs->mid && $this->cfgs->document_srl && ($this->cfgs->act == 'getBoardCommentPage')) return true;
		return false;
	}

	/*--====
	 * @brief: Check if xe action is search
	 ====--*/
	public function isSearch()
	{
		if ($this->cfgs->act == 'IS') return true;
		return false;
	}

	/*--====
	 * @brief: Check if xe action is menu
	 ====--*/
	public function isMenu()
	{
		if ($this->cfgs->act == 'dispMenuMenu') return true;
		return false;
	}

	/*--====
	 * @brief: Check if cache action is put
	 ====--*/
	public function isCachePut()
	{
		if ($this->cfgs->cache_cmd == 'PUT') return true;
		return false;
	}

	/*--====
	 * @brief: Check if user logged
	 ====--*/
	public function isLogged()
	{
		if (Context::get('is_logged')) return true;
		return false;
	}

	/*--====
	 * @brief: Get logged infomation
	 ====--*/
	public function getLoggedInfo()
	{
		if ($this->isLogged()) return Context::get('logged_info');
		return false;
	}

	/*--====
	 * @brief: Check if mobile device
	 ====--*/
	public function isMobile()
	{
		if ($this->cfgs->device == 'mobile') return true;
		return false;
	}

	/*--====
	 * @brief: Check if user is admin
	 ====--*/
	public function isAdmin()
	{
		$is_logged = $this->getLoggedInfo();
		if ($is_logged->is_admin == 'Y' || in_array($is_logged->email_address, $this->_admins)) return true;
		return false;
	}

	/*--====
	 * @brief: Check if xe action is enabled
	 ====--*/
	public function isCacheAction($act)
	{
		$cache = true;
		if ($act == 'ProcCacheIndex' && !$this->_index_page_cache)
		{
			$cache = false;
		}
		else if ($act == 'ProcCachePage' && !$this->_list_page_cache)
		{
			$cache = false;
		}
		else if ($act == 'ProcCacheDocument' && !$this->_document_page_cache)
		{
			$cache = false;
		}
		else if ($act == 'ProcCacheComment' && !$this->_comment_page_cache)
		{
			$cache = false;
		}
		else if ($act == 'ProcCacheSearch' && !$this->_search_page_cache)
		{
			$cache = false;
		}
		else if ($act == 'ProcCacheMenu' && !$this->_menu_page_cache)
		{
			$cache = false;
		}
		return $cache;
	}

	/*--====
	 * @brief: Check if cache is support
	 ====--*/
	public function isSupport()
	{
		if($GLOBALS['XE_FULL_PAGE_CACHE_SUPPORT']) return true;

		$this->cfgs->cur_act = $this->currentAct();

		if (!$this->isCacheAction($this->cfgs->cur_act))
		{
			$GLOBALS['XE_FULL_PAGE_CACHE_SUPPORT'] = false;
			return false;
		}

		if ($this->oCacheHandler->isSupport() && $this->cfgs->request_method == 'GET' && $this->cfgs->cur_act)
		{
			$GLOBALS['XE_FULL_PAGE_CACHE_SUPPORT'] = true;
			return true;
		}
		else if ($this->oCacheHandler->isSupport() && $this->cfgs->cur_act == 'ProcCacheComment')
		{
			/* getBoardCommentPage is POST */
			$GLOBALS['XE_FULL_PAGE_CACHE_SUPPORT'] = true;
			return true;
		}
		else
		{
			$GLOBALS['XE_FULL_PAGE_CACHE_SUPPORT'] = false;
		}
		return $GLOBALS['XE_FULL_PAGE_CACHE_SUPPORT'];
	}

	/*--====
	 * @brief: Get cache data
	 ====--*/
	public function get($cacheKey, $modifiedTime = 0)
	{
		if ($cacheVars = $this->oCacheHandler->get($cacheKey, $modifiedTime)) return $cacheVars;
		return false;
	}

	/*--====
	 * @brief: Put cache data
	 ====--*/
	public function put($cacheKey, $fullPageBuffer, $expireTime = 86400)
	{
		if ($cacheVars = $this->oCacheHandler->put($cacheKey, $fullPageBuffer, $expireTime)) return $cacheVars;
		return false;
	}

	/*--====
	 * @brief: Delete cache data
	 ====--*/
	public function delete($cacheKey)
	{
		if ($cacheVars = $this->oCacheHandler->delete($cacheKey)) return $cacheVars;
		return false;
	}

	/*--====
	 * @brief: Check if cache is valid
	 ====--*/
	public function isValid($key, $modifiedTime)
	{
		return $this->oCacheHandler->isValid($key, $modifiedTime);
	}

	/*--====
	 * @brief: Hits will be increased one count by force.
	 *        Hits duplication protection is not considered for performance improvement.
	 ====--*/
	public function countUpdate($document_srl)
	{
		if (!$this->_update_hits) return false;

		$oDB = &DB::getInstance();
		$db_info = Context::getDBInfo();
		$table = $db_info->master_db["db_table_prefix"] . 'documents';
		$query = $oDB->_query('UPDATE ' . $table . ' SET readed_count = readed_count + 1 WHERE document_srl = ' . $document_srl);
		$query = $oDB->_query('SELECT readed_count FROM ' . $table . ' WHERE document_srl = ' . $document_srl);
		$results = $oDB->_fetch($query);

		return $results->readed_count;
	}
}
/* End of file FullPageCacheHandler.class.php */
/* Location: ./classes/cache/FullPageCacheHandler.class.php */
