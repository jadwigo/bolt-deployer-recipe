# How to setup a deployer bolt site

This is a recipe on how to set up [deployer](https://deployer.org/) to manage a [bolt](https://bolt.cm/) site.

For the website itself you need a git repository with your Bolt 3 site.

To use deployer you must first install deployer, clone this repository and set up one or more servers. 

On the server you need access through SSH, a webserver like Nginx or Apache and a database server like Mysql.

## Basic usage

### Initial creation of shared files: dep bolt:init_shared

You can setup the shared files needed for a basic bolt installation by running `dep bolt:init_shared`. This will copy `.bolt.yml` and `config_local.yml` to the correct place on the server.

You can also create these files manually on your server.

### Deploy a site: dep deploy

After installation of this receipe you can run `dep deploy` on the console of your machine from the current installation directory of this recipe. This command will deploy the `development` stage by default.

### Rollback a deploy: dep rollback

This receipe will also make database snapshots of each deployment, these will be rolled back on each `dep rollback`

The database snaphots will not be cleaned up at the moment, so older database snapshots than the `keep_releases` amount will stay available unless you remove them manually.

### More commands

On the console you can run `dep list` to see the currently available commands.

## Basic installation

### On your machine

First install deployer from https://deployer.org/ on your machine.
Then run `git clone git@github.com:jadwigo/bolt-deployer-recipe.git my_directory` and `cd my_directory` to get into the correct place.

Make sure the `hosts.yml`, `.my.cnf` and `deploy.php` files exist

Optionally create `shared/.bolt.yml` and `shared/app/config/config_local.yml` files.

### Requirements

You also need the `mysqldump` command on the target machine - if it is not available the database snapshots won't work and must be disabled.

### On example.com

On the first deployment the path `/your/root/path/for/the/app/` will be checked, and in that path the directories `.dep`, `releases` and `shared` will be created. 

The path `/your/root/path/for/the/app/current` will be symlinked to the latest release directory in `releases/{n}`.  

The root directory (for Bolt 3 and up) usually is `/your/root/path/for/the/app/current/public`, so you need to prepare your host by setting up a webserver with the root of the website pointing to:  `/your/root/path/for/the/app/current/public`

## Credentials

It is probably best to create a specific deployment user account on the server and a specific backup administrator account in the database, as well as a normal mysql user for Bolt.

Make sure you can use ssh to connect passwordless (using the account `your_shell_user`) to your `yourhost.com`, and that `your_shell_user` has _passwordless sudo_ enabled **(this is required by deployer)**.

Set-up the account `your_shell_user` with the correct ssh keys to acces your git repository. See: https://deployer.org/docs/advanced/deploy-and-git for more information.

Configure the database user `your_mysql_user` so that user is allowed to create and read tables in `your_db_name` on the server.

Make sure the database user `your_mysql_backup_user` is allowed to perform mysqldump on `your_db_name` . 

If you want to use the rollbacks, the user `your_mysql_backup_user` must be allowed to create mysql databases and tables on the server.

## Configuration files

### hosts.yml

Your `hosts.yml` should look something like this:

```yml
example.com:
    hostname: example.com
    user: your_shell_user
    stage: development
    application: your_app_name
    git_tty: true
    keep_releases: 10
    cleanup_use_sudo: true
    shared_files: 
      - '.bolt.yml'
    keep_files: 
      - 'app/config/menu.yml'
      - 'app/config/taxonomy.yml'
    writable_dirs:   
      - 'app/config'
      - 'app/config/extensions'
      - 'app/database'
      - 'extensions'
      - 'public'
      - 'public/bolt-public'
    allow_anonymous_stats: false
    deploy_path: /your/root/path/for/the/app
    shared_files_path: /your/root/path/for/the/app/shared/files
    repository: git@gitlab.com:your/repository.git
    snapshots_dir: snapshots
    mysql_host: localhost
    mysql_db: your_db_name
```

Replace the `example.com`, `your_shell_user`, `your_app_name`, `/your/root/path/for/the/app/`, `git@github.com:your/repository.git` and `your_db_name` with your own variables.

The files in `keep_files` will be copied from the old release to the new release, so things that end-users will change in the site are copied to each new site.

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

If you use the custom files path `/your/root/path/for/the/app/shared/files` you need to set the same path in your hosts.yml

If you have custom bundles these need to be set in the `.bolt.yml` file as usual, so the `.bolt.yml` file might look a bit like this:

```yml
paths:
  cache: /your/root/path/for/the/app/shared/cache
  files: /your/root/path/for/the/app/shares/files
extensions:
  - Bolt\Extension\MyNamespace\MyBundle\MyBundleExtension
```

### shared/app/config/config_local.yml

The `config_local.yml` sets the database credentials for your site. For the database user you should have a separate account `your_mysql_user` that is different from `your_mysql_backup_user`.

```yml
database:
  driver: mysql
  databasename: your_db_name
  username: your_mysql_user
  password: your_mysql_user_password

canonical: example.com
```

The local configuration will be copied to the current release, so credentials do not need to be stored in git repositories.

### Local extension configuration

All local configuration files following the pattern `config/extensions/{extension}.{namespace}_local.yml` will be copied to the current release, so credentials do not need to be stored in git repositories.

## Deploying to multiple stages for acceptance and production

If you have multiple servers like development, testing, acceptance and production you can add multiple stages to your `hosts.yml` inventory.

```yml
.base: &base
  roles: app
  user: your_shell_user
  application: your_app_name
  git_tty: true
  keep_releases: 10
  cleanup_use_sudo: true
  shared_files: 
    - '.bolt.yml'
  keep_files: 
    - 'app/config/menu.yml'
    - 'app/config/taxonomy.yml'
  writable_dirs:   
    - 'app/config'
    - 'app/config/extensions'
    - 'app/database'
    - 'extensions'
    - 'public'
    - 'public/bolt-public'
  allow_anonymous_stats: false
  deploy_path: /your/root/path/for/the/app
  shared_files_path: /your/root/path/for/the/app/shared/files
  repository: git@gitlab.com:your/repository.git
  snapshots_dir: snapshots
  mysql_host: localhost
  mysql_db: your_db_name

development.example.com:
  <<: *base  
  hostname: development.example.com
  stage: development
  deploy_path: /var/www/development
  mysql_host: localhost
  mysql_db: your_dev_db_name

acceptance.example.com:
  <<: *base  
  hostname: another.server.example.com
  stage: acceptance
  deploy_path: /var/www/acceptance
  mysql_host: localhost
  mysql_db: your_acceptance_db_name

example.com:
  <<: *base
  hostname: example.com
  stage: production
  deploy_path: /var/www/example
  mysql_host: localhost
  mysql_db: your_production_db_name

```

The deployment of the sites goes to the `development` stage by default. For the other stages you can invoke deployment with `dep deploy acceptance` or `dep deploy production`. If you add multiple hosts to one environment, the deployment will go to all the servers in the chosen stage.
