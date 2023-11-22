# This file is part of the LB5JJ tools website.
# 
# The LB5JJ tools website is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by the Free
# Software Foundation, either version 3 of the License, or (at your option) any
# later version.
# 
# The LB5JJ tools website is distributed in the hope that it will be useful, but
# WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
# FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more
# details.
# 
# You should have received a copy of the GNU General Public License along with
# the LB5JJ tools website. If not, see <https://www.gnu.org/licenses/>. 

.PHONY: upload

upload: frontpage-upload match-upload

frontpage-upload:
	echo "put index.html" | sftp 6391046@sftp.sd5.gpaas.net:vhosts/tools.lb5jj.no/htdocs/

match-upload:
	echo "put -R match/" | sftp 6391046@sftp.sd5.gpaas.net:vhosts/tools.lb5jj.no/htdocs/

