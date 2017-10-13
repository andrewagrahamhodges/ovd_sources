/*
 * Copyright (C) 2013 Ulteo SAS
 * http://www.ulteo.com
 * Author David LECHEVALIER <david@ulteo.com> 2013
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

#ifndef VFSRULE_H_
#define VFSRULE_H_

#include <string>
#include <regex>


class VFSRule {
private:
	std::wstring rule;
	std::wstring destination;
	std::wregex* reg;
	bool translate;
	bool loopback;

public:
	VFSRule(const std::wstring& rule, const std::wstring& unionName, bool translate);
	virtual ~VFSRule();

	bool compile();

	bool match(const std::wstring& path);
	const std::wstring& getRule();
	const std::wstring& getDestination();
	bool needTranslate();
	bool isLoopBack();
};

#endif /* VFSRULE_H_ */
