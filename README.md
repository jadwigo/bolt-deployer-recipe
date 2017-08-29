# How to setup a deployer bolt site

This is a recipe on how to set up [deployer](https://deployer.org/) to manage a [bolt](https://bolt.cm/)

## Basic usage

Run `dep deploy` on the console of your machin in the instalaltion directory of this repository.

This receipe will also make database snapshots of each deployment, that will be rolled back on each `dep rollback`

## Basic installation

### On your machine

Install deployer from https://deployer.org/ on your machine.
Make sure the `hosts.yml`, `.my.cnf` and `deploy.php` files exist

### On example.com

On the first deploy the path `/your/root/path/for/the/app/` will be checked, and in that path the directories `.dep`, `releases` and `shared` will be created. 

The path `/your/root/path/for/the/app/current` will be symlinked to the latest release directory in `releases/#`.  

The root directory (for Bolt 3 and up) usually is `/your/root/path/for/the/app/current/public`, so you need to prepare your host by setting up a webserver with the root of the website pointing to:  `/your/root/path/for/the/app/current/public`

## Credentials

Make sure you can ssh connect passwordless (using the account `your_shell_user`) to your `yourhost.com`, and that `your_shell_user` has _passwordless sudo_ enabled **(this is required by deployer)**.

Also make sure the database user `your_mysql_user` user is allowed to create mysql databases and tables in those databases, and perform mysqldump.

It is probably best to create a specific deployment user account on the server and a specific backup administrator account in the database for these.

## Configuration files

### hosts.yml

Your `hosts.yml` should look something like this:

```yml
example.com:
    hostname: example.com
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

Replace the `example.com`, `your_shell_user`, `your_app_name`, `/your/root/path/for/the/app/`, `git@github.com:your/repository.git` and `your_db_name` with your own variables.

For more info, see https://deployer.org/docs/hosts#inventory-file

### .my.cnf

The `.my.cnf` file will be copied to the server with the first deploy. This is needed for the connection to the database server. Your `.my.cnf` should look something like this:

```ini
[client]
user="your_mysql_backup_user"
password="your_mysql_admin_password"
```

Replace the `your_mysql_backup_user` and `your_mysql_backup_user_password` with your own variables before the first deploy.

## Bolt configuration

There are some tweaks to the bolt configuration that you can prepare beforehand. Create the folder `/your/root/path/for/the/app/shared` and place the following files there:
 - `/your/root/path/for/the/app/shared/.bolt.yml`
 - `/your/root/path/for/the/app/shared/shared/app/config/config_local.yml`

You can also setup local configuration files for each extension as needed.
`/your/root/path/for/the/app/shared/shared/app/config/extensions/*_local.yml`

### shared/.bolt.yml

You should set the paths for the cache directory, and the files directory in the `.bolt.yml` file.

```yml
paths:
  cache: /your/root/path/for/the/app/shared/cache
  files: /your/root/path/for/the/app/shares/files
```

As above, the path `/your/root/path/for/the/app/` should be adapted for your situation.

### shared/app/config/config_local.yml

The `config_local.yml` should set the database credentials for your site. For the database suer you should have a separate account `your_mysql_user` that is different from `your_mysql_backup_user`.

```yml
database:
  driver: mysql
  databasename: your_db_name
  username: your_mysql_user
  password: your_mysql_user_password

canonical: example.com
```
