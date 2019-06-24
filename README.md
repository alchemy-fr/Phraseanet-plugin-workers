# Phraseanet-plugin-services
This is the repository for worker services in 4.1 PHRAS-2435

##To install

`bin/setup plugin:add ./plugin/path`

##To listen queues and launch corresponding service

`bin/console worker:execute`

 _Options :_
 
 ```
 --queue-name         The name of queues to be consuming (multiple values allowed)
 
 -p                   Preserve temporary payload file
 
 -m                   The max number of process allow to run ( default 4)
 ```

####List of queues to be created

- export-queue
- subdef-queue
- metadatas-queue
- logs-queue
- webhook-queue
- createrecord-queue
- assets-injest

####Route added

- POST /api/v1/upload/enqueue/

Authentification required

required Parameters:  assets , publisher, token

#### Admin plugin Configuration

Need to setup the Url uploader service in the phraseanet admin plugin to use the plugin with the uploader service.