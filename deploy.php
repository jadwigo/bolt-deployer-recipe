<?php

namespace Deployer;

require 'recipe/common.php';

if (!file_exists (__DIR__ . '/hosts.yml')) {
  die('Please create "' . __DIR__ . '/hosts.yml" before continuing.' . "\n");
}

if (!file_exists (__DIR__ . '/.my.cnf')) {
  die('Please create "' . __DIR__ . '/.my.cnf" before continuing.' . "\n");
}

/**
 * Set defaults
 */
set('default_stage', 'development');
set('default_branch', 'development');

/**
 * Hosts
 */
inventory('hosts.yml');

set('branch', function () {
  return input()->getOption('branch') ?: get('default_branch');
});

/**
 * Custom bins.
 */
set('bin/bash', function () {
    return locateBinaryPath('bash');
});
set('bin/tar', function () {
    return locateBinaryPath('tar');
});
set('bin/gzip', function () {
    return locateBinaryPath('gzip');
});
set('bin/mysql', function () {
    return locateBinaryPath('mysql');
});
set('bin/mysqldump', function () {
    return locateBinaryPath('mysqldump');
});
set('bin/mysqladmin', function () {
    return locateBinaryPath('mysqladmin');
});
set('bin/nut', '{{bin/php}} app/nut');


/**
 * Return backup path.
 */
set('backup_path', function () {
    if (!test("[ -d {{deploy_path}}/{{snapshots_dir}} ]")) {
        within("{{deploy_path}}", function () {
            writeln('<info>➤</info> setting up snapshots: {{snapshots_dir}}');
            run("mkdir {{deploy_path}}/{{snapshots_dir}}");
            upload('.my.cnf', '{{deploy_path}}/{{snapshots_dir}}/');
        });
    }
    if (!test("[ -f {{deploy_path}}/{{snapshots_dir}}/.my.cnf ]")) {
        upload('.my.cnf', '{{deploy_path}}/{{snapshots_dir}}/');
    }
    if (test("[ -d {{deploy_path}}/{{snapshots_dir}} ]")) {
        if(test("readlink {{deploy_path}}/{{snapshots_dir}}")) {
            $link = run("readlink {{deploy_path}}/{{snapshots_dir}}")->toString();
        } else {
            $link = get('deploy_path') . '/' . get('snapshots_dir');
        }
    } else {
        $link = get('current_path');
    }
    return $link;
});

/**
 * Custom Tasks
 */
task('test:current', function () {
    writeln('<info>➤</info> Current deploy path is {{deploy_path}}');
    writeln('<info>➤</info> Current release path is {{release_path}}');
    $releases_list = get('releases_list');
    set('releases_list', $releases_list);
    if(!empty($releases_list)) {
        set('current_release', $releases_list[0]);
    } else {
        set('current_release', 0);
    }
    writeln("<info>➤</info> current_release: {{current_release}}");
    writeln('<info>➤</info> Next release name is {{release_name}}');
})->desc('Show info about current releases.');

task('test:past', function () {
    $releases_list = get('releases_list');
    set('releases_list', $releases_list);
    if(count($releases_list)>1) {
        set('previous_release', $releases_list[1]);
        writeln("Previous release found {{previous_release}}");
    } else {
        set('previous_release', false);
        writeln("Previous release missing");
    }
})->desc('Show info about past releases');

/**
 * Bolt specific Tasks
 */
task('bolt:init_shared', function() {
    if (!file_exists (__DIR__ . '/shared/.bolt.yml')) {
      die('Please create "' . __DIR__ . '/shared/.bolt.yml" before continuing.' . "\n");
    }
    if (!file_exists (__DIR__ . '/shared/app/config/config_local.yml')) {
      die('Please create "' . __DIR__ . '/shared/app/config/config_local.yml" before continuing.' . "\n");
    }
    if (!test("[ -d {{deploy_path}}/shared/app/config ]")) {
        writeln('<info>➤</info> setting up shared config paths');
        run("mkdir -p {{deploy_path}}/shared/app/config/extensions");
        if (!test("[ -f {{deploy_path}}/shared/.bolt.yml ]")) {
            writeln('<info>➤</info> uploading .bolt.yml');
            upload('shared/', "{{deploy_path}}/shared/");
        }
        if (!test("[ -f {{deploy_path}}/shared/app/config/config_local.yml ]")) {
            writeln('<info>➤</info> uploading config_local.yml');
            upload('shared/app/config/',"{{deploy_path}}/shared/app/config");
        } else {
            writeln('<info>➤</info> config_local.yml already exists');
        }
    } else {
        writeln('<info>➤</info> shared config paths already exist');
        if (!test("[ -f {{deploy_path}}/shared/.bolt.yml ]")) {
            writeln('<info>➤</info> uploading .bolt.yml');
            upload('shared/', "{{deploy_path}}/shared/");
        } else {
            writeln('<info>➤</info> .bolt.yml already exists');
        }
        if (!test("[ -f {{deploy_path}}/shared/app/config/config_local.yml ]")) {
            writeln('<info>➤</info> uploading config_local.yml');
            upload('shared/app/config/',"{{deploy_path}}/shared/app/config");
        } else {
            writeln('<info>➤</info> config_local.yml already exists');
        }
    }
})->desc('Prepare shared configuration files and folders.');

task('bolt:vendors', function() {
    run('cd {{release_path}} && {{bin/composer}} {{composer_options}} --ignore-platform-reqs');
})->desc('Update bolt vendors');

task('bolt:extensions', function() {
    run('cd {{release_path}}/extensions && {{bin/composer}} {{composer_options}} --ignore-platform-reqs ');
})->desc('Install extension updates');

task('bolt:dbupdate', function() {
    // running app/nut database update in release folder
    run('cd {{release_path}} && {{bin/nut}} database:update', [ 'tty' => true ]);
})->desc('Run database updates');

task('bolt:localconfig', function() {
    // Copy bolt localconfig
    within('{{deploy_path}}', function() {
        if (test("[ -f {{deploy_path}}/shared/app/config/config_local.yml ]")) {
            run('cp {{deploy_path}}/shared/app/config/config_local.yml {{release_path}}/app/config/.');
        }
        if (test("[ -d {{deploy_path}}/shared/app/config/extensions ]")) {
            run('cp {{deploy_path}}/shared/app/config/extensions/*_local.yml {{release_path}}/app/config/extensions/.');
        }
        run('chmod -R a+rw {{release_path}}/app/config');
    });
})->desc('Copy shared config files to current release.');

task('bolt:filespath', function() {
    if (!test("[ -d {{release_path}}/public/files ]")) {
        // Symlink shared files directory to current release
        run('ln -s {{shared_files_path}} {{release_path}}/public/files');
    } else {
        writeln('<info>➤</info> Shared files folder is already set-up');
    }
    if (!test("[ -d {{release_path}}/public/thumbs ]")) {
        // Symlink thumb files directory to current release
        run('ln -s {{thumb_files_path}} {{release_path}}/public/thumbs');
    } else {
        writeln('<info>➤</info> Shared files folder is already set-up');
    }
})->desc('Symlink shared files and thumbnail directory to current release.');

task('bolt:keepfiles', function() {
    $keep_files = get('keep_files');
    $release_path = get('release_path');
    $current_path = get('current_path');
    if($release_path != $current_path) {
        // Copying keep_files along releases
        if (test("[ -d {{current_path}} ]")) {
            if (!empty($keep_files)) {
                foreach ($keep_files as $currentfile) {
                    set('currentfile', $currentfile);
                    run(
                      'cp {{current_path}}/{{currentfile}} {{release_path}}/{{currentfile}}'
                    );
                }
            }
        } else {
            writeln('no curent directory: {{current_path}}');
        }
    } else {
        writeln('<info>➤</info> Not copying files along releases.');
        foreach($keep_files as $currentfile) {
            set('currentfile', $currentfile);
            writeln('not doing this: cp {{current_path}}/{{currentfile}} newrelease/{{currentfile}}');
        }
    }

})->desc('Keep some files along releases');


task('bolt:fix_access', function() {
    // Fix access control
    within('{{release_path}}', function() {
        if (test("[ -d app/config ]")) {
            run('sudo chmod -R a+rw app/config', [ 'tty' => true ]);
        }
        if (test("[ -d public/thumbs ]")) {
            run('sudo chmod -R a+rw public/thumbs', [ 'tty' => true ]);
        }
    });
})->desc('Set rw access control for config and thumbs directories');

/**
 * Database tasks - test, snapshot and restore
 */
task('db:test', function () {
    writeln('<info>➤</info> db test');
    writeln('<info>➤</info> release path: {{current_path}}');
    $backup_path = get('backup_path');
    writeln('<info>➤</info> backup {{backup_path}}');
    $releases_list = get('releases_list');
    set('releases_list', $releases_list);
    if(!empty($releases_list)) {
        set('current_release', $releases_list[0]);
    } else {
        set('current_release', 0);
    }
    writeln("<info>➤</info> current_release: {{current_release}}");
    set('dumpfile', get('backup_path') . '/' . get('current_release') .'.sql');
    writeln('<info>➤</info> current dumpfile: {{dumpfile}}');
})->desc('Show database snapshot');

task('db:snapshot', function () {
    // Database snapshot
    $backup_path = get('backup_path');
    $releases_list = get('releases_list');
    set('releases_list', $releases_list);
    if(!empty($releases_list)) {
        set('current_release', $releases_list[0]);
    } else {
        set('current_release', 0);
    }
    set('dumpfile', get('backup_path') . '/' . get('current_release') .'.sql');
    run('{{bin/mysqldump}} --defaults-extra-file={{backup_path}}/.my.cnf {{mysql_db}} > {{dumpfile}}');
})->desc('Run a database snapshot for the current release');

task('db:restore', function () {
    // TODO set the dumpfile filename to something with the _PREVIOUS_ release
    $backup_path = get('backup_path');
    $releases_list = get('releases_list');
    set('releases_list', $releases_list);
    if(count($releases_list)>1) {
        set('previous_release', $releases_list[1]);
        set('dumpfile', get('backup_path') . '/' . $releases_list[1] .'.sql');
        if(test('[ -f {{dumpfile}} ]')) {
            writeln('<info>✘</info> restoring db from: {{dumpfile}}');
            // create a temporary database for import
            run("{{bin/mysql}} --defaults-extra-file={{backup_path}}/.my.cnf -e 'CREATE DATABASE IF NOT EXISTS {{mysql_db}}_temp;'");
            // import everything into the temporary database
            run('{{bin/mysql}} --defaults-extra-file={{backup_path}}/.my.cnf {{mysql_db}}_temp < {{dumpfile}}');

            $tables = run('{{bin/mysql}} --defaults-extra-file={{backup_path}}/.my.cnf -B -N -e "SHOW TABLES;" {{mysql_db}}_temp');
            $tables = explode("\n", $tables);
            foreach($tables as $table) {
                set('table', $table);
                writeln("<info>✘</info> table {{mysql_db}}_temp.{{table}}");
                // writeln("{{bin/mysql}} --defaults-extra-file={{backup_path}}/.my.cnf -e 'RENAME {{mysql_db}}_temp.{{table}} {{mysql_db}}.{{table}};'");
                run("{{bin/mysql}} --defaults-extra-file={{backup_path}}/.my.cnf -e 'DROP TABLE IF EXISTS {{mysql_db}}.{{table}}; RENAME TABLE {{mysql_db}}_temp.{{table}} TO {{mysql_db}}.{{table}};'");
            }
            // cleanup the temporary database
            run('{{bin/mysqladmin}} --defaults-extra-file={{backup_path}}/.my.cnf -f drop {{mysql_db}}_temp');
            // writeln('deploypath:{{deploy_path}} releasepath:{{release_path}} next:{{release_name}} prev:{{previous_release}} {{dumpfile}}');
        } else {
            writeln('<fg=red>✘</fg=red><fg=yellow> restore failed: {{dumpfile}} not found</fg=yellow>');
            invoke('deploy:failed');
        }
    } else {
        set('previous_release', false);
        writeln('<fg=red>✘</fg=red><fg=yellow> restore failed: no previous release found</fg=yellow>');
        invoke('deploy:failed');
    }
})->desc('Restore the current database from a snapshot');

task('db:list', function () {
    writeln('<info>➤</info> list {{release_path}}');
    within('{{deploy_path}}', function () {
        writeln('<info>➤</info> list {{release_path}}');
    });
})->desc('List database snapshots');

/**
 * Shortcut to show configured hosts
 */
task('hosts', [
  'config:hosts'
])->desc('Show all configured hosts and builds');

/**
 * The bolt specific steps of deployment
 */
task('deploy:bolt', [
  'bolt:vendors',
  'bolt:extensions',
  'bolt:localconfig',
  'bolt:filespath',
  'bolt:keepfiles',
  'bolt:dbupdate'
])->desc('Run bolt specific deploy tasks');

/**
 * Main task
 */
task('deploy', [
    'deploy:info',
    'deploy:prepare',
    'deploy:lock',
    'deploy:release',
    'deploy:update_code',
    'deploy:shared',
    'deploy:bolt',
    'deploy:symlink',
    'deploy:unlock',
    'cleanup',
])->desc('Deploy your project');

/**
 * Trigger success after deploy
 */
after('deploy', 'success');

/**
 * Unlock and continue after failed deploy
 */
after('deploy:failed', 'deploy:unlock');

/**
 * Inject database snapshot after lock has activated
 */
after('deploy:lock', 'db:snapshot');

/**
 * Rollback needs writable directories
 */
before('rollback', 'bolt:fix_access');

/**
 * Reset the database to the previous version after rollback
 */
after('rollback', 'db:restore');
