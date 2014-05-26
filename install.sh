#! /bin/bash
#
# @file: install.sh
# @brief: Install script for implement xpressengine full page cache
# @author: YoungJoo.Kim <http://superlinuxer.com>
# @version: $Revision:
# @date: 20140301

PATH="/sbin:/usr/sbin:/bin:/usr/bin"
export PATH

cmd=$1
xe_dir=$2

G_RET_VAL=
G_RET_STR=

usage() {
	echo "Usage: $0 {install|uninstall} [Installed xpressengine path]"
	echo
	echo "Examples"
	echo "         $0 install /home/httpd/vhosts/xpressengine.com"
	echo "         $0 uninstall /home/httpd/vhosts/xpressengine.com"
	exit 1
}

checkXe() {
	if [ ! -f "$xe_dir/files/config/db.config.php" ]; then
		echo "You need to XE install."
		exit 1
	fi
	return 0
}

getHash() {
	local _file=$1 _hash=
	_hash=$(md5sum $_file)
	G_RET_STR=${_hash%% *}
}

install() {
	checkXe

	getHash "config/config.user.inc.php"
	_fpc_hash=$G_RET_STR

	pushd $xe_dir
	if [ -f "config/config.user.inc.php" -a ! -f "config/org.config.user.inc.php" ]; then
		getHash "config/config.user.inc.php"
		_org_hash=$G_RET_STR
		[ "$_fpc_hash" != "$_org_hash" ] && \mv config/config.user.inc.php config/org.config.user.inc.php
	fi
	popd

	\cp -af config/config.user.inc.php $xe_dir/config/
	\cp -af index.fpc.php $xe_dir/
	\cp -af classes/cache/FullPageCacheHandler.class.php $xe_dir/classes/cache/

	pushd $xe_dir
	if [ -f "index.fpc.php" ]; then
		[ ! -f "index.org.php" ] && \mv index.php index.org.php
		[ ! -L "index.php" ] && \ln -sf index.fpc.php index.php
	fi
	popd
	echo "# $cmd completed."
}

uninstall() {
	checkXe

	\rm -f $xe_dir/index.fpc.php
	\rm -f $xe_dir/config/config.user.inc.php
	\rm -f $xe_dir/classes/cache/FullPageCacheHandler.class.php

	pushd $xe_dir
	if [ -f "config/org.config.user.inc.php" ]; then
		\mv config/org.config.user.inc.php config/config.user.inc.php
	fi

	if [ -L "index.php" ]; then
		\unlink index.php
		\mv index.org.php index.php 
	fi
	popd
	echo "# $cmd completed."
}

case "$1" in
	install)
		install	
		;;
	uninstall)
		uninstall
		;;
	*)
		usage
esac

