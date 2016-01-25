##INSTALL
```shell
git clone https://github.com/TeoChoi/HostMT.git
cp HostMT/mhosts.php /usr/bin/mhosts
```


##Help List

 ```bash
	-l                              list of all hosts
	-a [local]                      add a hosts
	-s [local, local1, local2...]   switch local env hosts
	-v [local]                      view local env hosts
	-e [local]                      edit local env hosts data
	-r                              restore the default hosts
	--current                       list of the active hosts
	--version                       the version 
 ```
 
##Document
1. show list `mhosts -l`
2. create a hosts and named test `mhosts -a test`
3. view a hosts details `mhosts -v test`
4. edit a hsots `mhosts -e test`
5. switch a hosts `mhosts -s test1`, switch batch hosts `mhosts -s test1 test2 ...`
6. restore the original hosts `mhosts -r`

 
 