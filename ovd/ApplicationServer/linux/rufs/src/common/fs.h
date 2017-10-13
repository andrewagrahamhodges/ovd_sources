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
#ifndef _COMMON_FS_
#define _COMMON_FS_

#include <linux/limits.h>
#include "types.h"

#define MAX_LINE_SIZE 1024


int file_open(const char* filename);
void file_close(int fd);
bool file_delete(const char* filename);
int file_write(int fd, char* buffer, size_t size);
off_t file_seek(int fd, off_t offset);
off_t file_getOffset(int fd);
size_t file_read(int fd, char* buffer, size_t size);
size_t file_size(char* filename);
bool file_readLine(int fd, char* line);
char* file_getShortName(const char* filename);
bool fs_expandPath(const char* source, char* destination);
bool fs_mkdir(const char* path);
bool fs_exist(const char* path);
bool fs_isdir(const char* path);
bool fs_mountbind(const char* src, const char* dst);
bool fs_umount(const char* dst);
bool fs_setCurrentDir(const char* dir);
char* fs_getRoot(const char* path);
char* fs_join(const char* p1, const char* p2);
long long fs_getSpace(const char* path);
bool fs_mkdirs(const char* file);
bool fs_rmdirs(const char* path);

#endif
