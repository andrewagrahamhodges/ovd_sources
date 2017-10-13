/*
 * Copyright (C) 2012 Ulteo SAS
 * http://www.ulteo.com
 * Author David LECHEVALIER <david@ulteo.com> 2012
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; version 2
 * of the License.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

#ifndef _CONFIGURATION_H_
#define _CONFIGURATION_H_

#include "common/list.h"
#include "common/types.h"
#include "common/fs.h"
#include "common/ini.h"
#include "common/regexp.h"



#define PACKAGE_VERSION            "0.1"
#define DEFAULT_CONFIGURATION_PATH "/etc/ulteo/rufs/default.conf"
#define MAIN_CONFIGURATION_SECTION "main"
#define TRANS_CONFIGURATION_SECTION "translation"
#define MAIN_UNION_CONFIGURATION_KEY "union"
#define MAIN_BIND_CONFIGURATION_KEY "bind"
#define MAIN_BIND_DESTINATION_CONFIGURATION_KEY "bindDestination"
#define MAIN_SHARE_LIST_CONFIGURATION_KEY "sharesList"
#define MAIN_SHARE_LIST_QUOTA_GRACE "sharesQuotaGrace"
#define MAIN_PID_FILE "pidFile"
#define MAIN_UMASK "umask"
#define MAIN_PERMISSION_MASK "permissionMask"


#define LOG_CONFIGURATION_SECTION "log"
#define LOG_LEVEL_CONFIGURATION_KEY "level"
#define LOG_PROGRAM_CONFIGURATION_KEY "program"
#define LOG_DEVEL_CONFIGURATION_KEY "enableDevelOutput"
#define LOG_STDOUT_CONFIGURATION_KEY "enableStdOutput"
#define LOG_OUTFILE_CONFIGURATION_KEY "outputFilename"

#define UNION_PATH_CONFIGURATION_KEY "path"
#define UNION_ACCEPT_CONFIGURATION_KEY "accept"
#define UNION_REJECT_CONFIGURATION_KEY "reject"
#define UNION_RSYNC_CONFIGURATION_KEY "rsync"
#define UNION_CREATE_PARENT_KEY "createParent"
#define UNION_RFILTER_CONFIGURATION_KEY "rsync_filter"
#define UNION_ACCEPT_SYMLINK_KEY "acceptSymlink"
#define UNION_DELETE_ON_END_KEY "deleteOnEnd"

#define RULE_CONFIGURATION_SECTION "rules"


typedef struct _Union {
	char name[256];
	char path[PATH_MAX];
	char rsync_src[PATH_MAX];
	char rsync_filter_filename[PATH_MAX];
	bool acceptSymlink;
	bool deleteOnEnd;
	bool createParentDirectory;
	List* accept;
	List* reject;
} Union;


typedef struct _Rule {
	Union* u;
	Regexp* reg;
} Rule;


typedef struct _Translation {
	char in[PATH_MAX];
	char out[PATH_MAX];
} Translation;


typedef struct _Configuration {
	char* user;
	char* configFile;
	char* shareFile;
	char* pidFile;
	int umask;
	int permission_mask;
	long long shareGrace;
	bool bind;
	char* source_path;
	char* destination_path;
	char bind_path[PATH_MAX];
	List* unions;
	List* translations;
	List* rules;
} Configuration;


Configuration* configuration_new();
bool configuration_free(Configuration* conf);
bool configuration_parse(Configuration* conf);
void configuration_dump (Configuration* conf);
Union* configuration_getUnion(Configuration* conf, const char* unionName);


#endif
