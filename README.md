Xpressengine full page cache handler
==========

[![License](http://img.shields.io/badge/license-GNU%20LGPL-brightgreen.svg)](http://www.gnu.org/licenses/gpl.html)

xe-hook-fpc is a very simple full page cache handler for xpressengine with memcached.
Note that it is not a module or addon.
It purpose is to improving performance of very heavy xpressengine.
This program has some problems such as hits increasing and list updating.
So you should be taken into account carefully.
Have a good luck:)

## Dependencies
* Xpressengine(1.7.4+)
* Memcached

## Xpressengine memcached setting

```
shell> vi [Installed xpressengine path]/files/config/db.config.php
```

```
'use_object_cache' => 'memcache://localhost:11211'
```

## Installation

```
shell> git clone git://github.com/vozlt/xe-hook-fpc.git
```

```
shell> cd xe-hook-fpc
```

```
shell> bash install.sh install [Installed xpressengine path]
```

## Uninstallation

```
shell> cd xe-hook-fpc
```

```
shell> bash install.sh uninstall [Installed xpressengine path]
```

## Number of Function Calls

| Actions           | Disable           | Enable            |
| ----------------- | ----------------- | ----------------- |
| document          | 7177              | 518               |
| list              | 4862              | 455               |

This analysis was using xhprof.

## Default cache expires

| Actions           | expires(sec)      |
| ----------------- | ----------------- |
| index             | 300               |
| list              | 3600              |
| document          | 3600              |
| comment           | 3600              |
| searched result   | 3600              |
| menu              | 3600              |

Cache expiration setting file path(If the value is set to 0, will be not cache.)
```
shell> vi [Installed xpressengine path]/config/config.user.inc.php
````

```php
define('__XE_HOOK_FPC_INDEX_EXPIRES__', 300);     /* index's cache expires time(sec) */
define('__XE_HOOK_FPC_LIST_EXPIRES__', 3600);     /* list's cache expires time(sec) */
define('__XE_HOOK_FPC_DOCUMENT_EXPIRES__', 3600); /* document's cache expires time(sec) */
define('__XE_HOOK_FPC_COMMENT_EXPIRES__', 3600);  /* comment's cache expires time(sec) */
define('__XE_HOOK_FPC_SEARCH_EXPIRES__', 3600);   /* searched result's cache expires time(sec) */
define('__XE_HOOK_FPC_MENU_EXPIRES__', 3600);     /* menu's cache expires time(sec) */
```

## Display cache key

```
shell> vi layouts/[your layout path]/layout.html
```

```
<!--fpc:debug_info:s--><!--fpc:debug_info:e-->
```

If xe-hook-fpc is enabled will be displayed as follow.
```
<!--debug_info{fpc:pc:ProcCacheDocument:board:1:0:login}-->
```

## Author
YoungJoo.Kim <http://superlinuxer.com>
