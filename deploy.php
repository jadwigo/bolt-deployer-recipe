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
 * Hosts
 */
inventory('hosts.yml');


set('host', 'guust');
set('default_stage', 'production');

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


/**
 * Custom Tasks
 */
task('test', function () {
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
    invoke('config:dump');
})->desc('Test if I am working.');

task('past', function () {
    $releases_list = get('releases_list');
    set('releases_list', $releases_list);
    if(count($releases_list)>1) {
        set('previous_release', $releases_list[1]);
        writeln("Previous release found {{previous_release}}");
    } else {
        set('previous_release', false);
        writeln("Previous release missing");
        invoke('config:dump');
    }
})->desc('Show info about releases');

task('bolt:vendors', function() {
    run('cd {{release_path}} && {{bin/composer}} {{composer_options}} --ignore-platform-reqs');
})->desc('Update bolt vendors');

task('bolt:extensions', function() {
    run('cd {{release_path}}/extensions && {{bin/composer}} {{composer_options}} --ignore-platform-reqs ');
})->desc('Install extension updates');

task('bolt:localconfig', function() {
    writeln('<info>➤</info> Copy bolt localconfig');
    within('{{deploy_path}}', function() {
        run('cp {{deploy_path}}/shared/app/config/config_local.yml {{release_path}}/app/config/.', [ 'tty' => true ]);
        run('cp {{deploy_path}}/shared/app/config/extensions/*_local.yml {{release_path}}/app/config/extensions/.', [ 'tty' => true ]);
        run('chmod -R a+rw {{release_path}}/app/config', [ 'tty' => true ]);
    });
})->desc('Copy shared config files to current release.');

task('bolt:fix_access', function() {
    writeln('<info>➤</info> Fix access control');
    within('{{release_path}}', function() {
        run('sudo chmod -R a+rw app/config public/thumbs', [ 'tty' => true ]);
    });
})->desc('Set rw access control for all directories');

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
    writeln('<info>➤</info> Database snapshot');
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
            run('{{bin/mysqladmin}} -u {{mysql_user}} -p{{mysql_pass}} -f drop {{mysql_db}}_temp');
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
 * Main task
 */
task('deploy', [
    'deploy:info',
    'deploy:prepare',
    'deploy:lock',
    'deploy:release',
    'deploy:update_code',
    'deploy:shared',
    'bolt:vendors',
    'bolt:extensions',
    'bolt:localconfig',
    'deploy:symlink',
    'deploy:unlock',
    'cleanup',
])->desc('Deploy your project');

after('deploy', 'success');
after('deploy:failed', 'deploy:unlock');

after('deploy:lock', 'db:snapshot');
before('rollback', 'bolt:fix_access');
after('rollback', 'db:restore');