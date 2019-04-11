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


or create a service daemon /etc/systemd/system/alchemyWorker.service


`````
[Unit]
Description= Alchemy phraseanet worker

[Service]

Type=simple
User=vagrant

ExecStart=/usr/bin/php -f /vagrant/bin/console workers:run-dispatcher

TimeoutSec=0
PIDFile=/var/run/server.pid
KillMode=mixed
Restart=on-failure

RestartSec=42s

[Install]
WantedBy=multi-user.target
`````


`systemctl start alchemyWorker.service`

