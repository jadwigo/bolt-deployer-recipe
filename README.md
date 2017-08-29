# DEPLOYER

This is an explanation on how to set up [deployer](https://deployer.org/) to manage a [bolt](https://bolt.cm/)

## setup

### your machine

Install deployer from https://deployer.org/ on your machine.
Make sure the `hosts.yml`, `.my.cnf` and `deploy.php` files exist

### yourhost.com

On the first deploy the path `/your/root/path/for/the/app/` will be checked, and in that path the directories `.dep`, `releases` and `shared` will be created. 

The path `/your/root/path/for/the/app/current` will be symlinked to the latest release directory in `releases/#`.  

The root directory (for Bolt 3 and up) usually is `/your/root/path/for/the/app/current/public`, so you need to prepare your host by setting up a webserver with the root of the website pointing to:  `/your/root/path/for/the/app/current/public`

## credentials

Make sure you can ssh connect passwordless (using the account `your_shell_user`) to your `yourhost.com`, and that `your_shell_user` has passwordless sudo enabled (this is required by deployer).

Also make sure the database user `your_mysql_user` user is allowed to create mysql databases and tables in those databases, and perform mysqldump.

## configuration files

### hosts.yml

Your `hosts.yml` should look something like this:

```yml
yourhost.com:
    hostname: yourhost.com
    user: your_shell_user
    configFile: ~/.ssh/config
    stage: production
    roles: site
    application: your_app_name
    git_tty: true
    keep_releases: 10
    shared_files: [ '.bolt.yml' ]
    writable_dirs: [ 'app/config', 'app/config/extensions', 'app/config/extensions/hierarchicalroutes', 'app/database', 'extensions', 'public', 'public/bolt-public', 'public/thumbs' ]
    allow_anonymous_stats: false
    deploy_path: /your/root/path/for/the/app
    repository: git@gitlab.com:your/repository.git
    snapshots_dir: snapshots
    mysql_host: localhost
    mysql_db: your_db_name
```

Replace the `yourhost.com`, `your_shell_user`, `your_app_name`, `/your/root/path/for/the/app/`, `git@github.com:your/repository.git` and `your_db_name` with your own variables.

For more info, see https://deployer.org/docs/hosts#inventory-file

### .my.cnf

The `.my.cnf` file will be copied to the server with the first deploy. This is needed for the connection to the database server. Your `.my.cnf` should look something like this:

```ini
[client]
user="your_mysql_user"
password="your_password"
```

Replace the `your_mysql_user` and `your_password` with your own variables before the first deploy.

