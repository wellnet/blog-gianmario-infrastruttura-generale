# Project Local Infrastructure #

This project containes code and configuration to run our application in a local development environment.

## Set up ##

### Clone repository & setup ###
```
$ git clone git@github.com:wellnet/blog-gm-local-infrastructure.git
$ cd blog-gm-local-infrastructure
$ composer install
```

### Download others project's code ###
```
$ cp docker-compose.yml.dist docker-compose.yml
$ git submodule update --init --recursive
```

### Start up docker containers ###
```
$ docker-compose up -d
```

### Set up projects build files ###
```
$ cp cp build/build.pr1.local.default.yml.dist build/build.pr1.local.default.yml
$ cp cp build/build.pr2.local.default.yml.dist build/build.pr2.local.default.yml
$ cp cp build/build.pr3.local.default.yml.dist build/build.pr3.local.default.yml
```

### Projects database dump files ###
Downloads the application's database dumps ad save them in build/dbs directory. The dump filename must have the same used in the respective build file (property: databases.drupal.dump_filename), for example for pr1 the dump filename must be `pr1.drupal.sql`.

### Init project ###
This comand have to be run only when you setup you local installation the first time.
```
$ vendor/bin/robo init:ag
```

### Build Projects ###
```
$ vendor/bin/robo build:pr1
$ vendor/bin/robo build:pr2
$ vendor/bin/robo build:pr3
```