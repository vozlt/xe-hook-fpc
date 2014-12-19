<?php
/**--====
 * @file: config.user.inc.php
 * @brief: config for implement xpressengine full page cache
 * @author: YoungJoo.Kim <vozlt@vozlt.com>
 * @version:
 * @date: 20140301
 ====--**/

/*-- original config.user.inc.php load --*/
if(file_exists(_XE_PATH_ . 'config/org.config.user.inc.php'))
{
	require _XE_PATH_ . 'config/org.config.user.inc.php';
}

/*-- FullPageCacheHandler broken protection set(xpressengine internal compress disabled) --*/
define('__OB_GZHANDLER_ENABLE__', 0);

/*-- 
 * @brief: xe-hook-fpc run flag
 * @value:
 *         0 =: xe-hook-fpc disabled
 *         1 =: xe-hook-fpc enabled
 * --*/
define('__XE_HOOK_FPC__', 1);

/*--
 * @brief: Action's cache expiration time
 * @value:
 *         0 =:  no cache
 *         1 <:  cache time(second)
 --*/
define('__XE_HOOK_FPC_INDEX_EXPIRES__', 300);       /* index's cache expires time(sec) */
define('__XE_HOOK_FPC_LIST_EXPIRES__', 3600);       /* list's cache expires time(sec) */
define('__XE_HOOK_FPC_DOCUMENT_EXPIRES__', 3600);   /* document's cache expires time(sec) */
define('__XE_HOOK_FPC_COMMENT_EXPIRES__', 3600);    /* comment's cache expires time(sec) */
define('__XE_HOOK_FPC_SEARCH_EXPIRES__', 3600);     /* searched result's cache expires time(sec) */
define('__XE_HOOK_FPC_MENU_EXPIRES__', 3600);       /* menu's cache expires time(sec) */

/*-- hits increase flag(true|false) --*/
define('__XE_HOOK_FPC_UPDATE_HITS__', 1);

/*-- logged cache flag(true|false) --*/
define('__XE_HOOK_FPC_LOGGED__', 0);

/* End of file config.user.inc.php */
/* Location: ./config/config.user.inc.php */
