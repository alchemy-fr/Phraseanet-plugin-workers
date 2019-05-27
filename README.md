# Phraseanet-plugin-services
This is the repository for worker services in 4.1 PHRAS-2435

**To install**

`bin/setup plugin:add ./plugin/path`

**To listen queues and launch corresponding service**

`bin/console worker:execute`

 _Options :_
 
 ```
 --queue-name         The name of queues to be consuming (multiple values allowed)
 
 -p                   Preserve temporary payload file
 
 -m                   The max number of process allow to run ( default 4)
 ```


