{
  buildEnv,
  infectionPhar,
  php81,
  php82,
  php83,
  php84,
  php85,
  pkgs,
  src,
}:

let
  inherit (pkgs) lib;
  # Reuse the flake's filtered source so local vendor trees, temporary files,
  # and secrets can never become inputs to a fixed-output repository.
  repositoryRoot = src;
  vendorHashes = import ./vendor-hashes.nix;

  php = {
    php81 = buildEnv {
      php = php81;
      withPcov = false;
    };
    php82 = buildEnv {
      php = php82;
      withPcov = false;
    };
    php83 = buildEnv {
      php = php83;
      withPcov = false;
    };
    php84 = buildEnv {
      php = php84;
      withPcov = false;
    };
    php85 = buildEnv {
      php = php85;
      withPcov = false;
    };
  };
  php84WithPcov = buildEnv {
    php = php84;
    withPcov = true;
  };
  php84WithXdebug = buildEnv {
    php = php84;
    withPcov = false;
    withXdebug = true;
  };

  rootComposerSource = lib.cleanSourceWith {
    src = repositoryRoot;
    filter = path: type: type == "directory" || baseNameOf path == "composer.json";
  };

  mkToolComposerSource =
    tool:
    lib.cleanSourceWith {
      src = repositoryRoot;
      filter =
        path: type:
        let
          relativePath = lib.removePrefix "${toString repositoryRoot}/" (toString path);
        in
        type == "directory"
        || relativePath == "composer.json"
        || relativePath == "tools/${tool}/composer.json"
        || relativePath == "tools/${tool}/composer.lock";
    };

  mkComposerRepository =
    {
      composerLock,
      name,
      php,
      postUnpack ? "",
      source ? rootComposerSource,
    }:
    let
      lockDigest = builtins.substring 0 12 (builtins.hashFile "sha256" composerLock);
    in
    php.mkComposerRepository {
      # Include the lock digest in the fixed-output path. If a cached repository
      # has the old vendorHash, a changed lock must still rebuild so Nix can
      # report the replacement hash.
      pname = "phpstan-lost-in-translation-${name}-${lockDigest}";
      version = "1";
      src = source;
      inherit composerLock postUnpack;
      vendorHash = vendorHashes.${name};
      composerNoDev = false;
      composerNoPlugins = true;
      composerNoScripts = true;
      composerStrictValidation = true;
    };

  repositories = {
    root = mkComposerRepository {
      name = "root";
      php = php.php81;
      composerLock = ../composer.lock;
    };
    laravel9 = mkComposerRepository {
      name = "laravel9";
      php = php.php81;
      composerLock = ./composer/laravel-9.lock;
    };
    laravel11 = mkComposerRepository {
      name = "laravel11";
      php = php.php82;
      composerLock = ./composer/laravel-11.lock;
    };
    laravel12 = mkComposerRepository {
      name = "laravel12";
      php = php.php82;
      composerLock = ./composer/laravel-12.lock;
    };
    laravel13 = mkComposerRepository {
      name = "laravel13";
      php = php.php83;
      composerLock = ./composer/laravel-13.lock;
    };
    lowest = mkComposerRepository {
      name = "lowest";
      php = php.php81;
      composerLock = ./composer/lowest.lock;
    };
    eris = mkComposerRepository {
      name = "eris";
      php = php.php81;
      source = mkToolComposerSource "eris";
      composerLock = ../tools/eris/composer.lock;
      postUnpack = ''
        sourceRoot="$sourceRoot/tools/eris"
      '';
    };
    akashi = mkComposerRepository {
      name = "akashi";
      php = php.php82;
      source = mkToolComposerSource "akashi";
      composerLock = ../tools/akashi/composer.lock;
      postUnpack = ''
        sourceRoot="$sourceRoot/tools/akashi"
      '';
    };
  };

  mkPhpCheck =
    {
      command,
      composerLock ? ../composer.lock,
      composerNoDev ? false,
      name,
      php,
      postUnpack ? "",
      prepare ? "",
      repository ? repositories.root,
      result ? ''touch "$out/passed"'',
      source ? src,
    }:
    pkgs.stdenvNoCC.mkDerivation {
      pname = "phpstan-lost-in-translation-${name}";
      version = "1";
      src = source;
      inherit composerLock composerNoDev postUnpack;
      composerRepository = repository;
      composerNoPlugins = true;
      composerNoScripts = true;
      composerStrictValidation = true;
      nativeBuildInputs = [
        php
        php.packages.composer-local-repo-plugin
        php.composerHooks.composerInstallHook
      ];
      strictDeps = true;
      dontPatchShebangs = true;
      doCheck = true;
      checkPhase = ''
        runHook preCheck
        runHook postCheck
      '';
      installPhase = ''
        export COMPOSER_DISABLE_NETWORK=1
        export COMPOSER_NO_AUDIT=1
        export HOME="$TMPDIR/home"
        export XDG_CACHE_HOME="$TMPDIR/cache"
        mkdir -p "$HOME" "$XDG_CACHE_HOME"

        runHook preInstall

        ${prepare}
        ${command}

        find "$out" -mindepth 1 -delete
        ${result}

        runHook postInstall
      '';
    };

  mkSimpleCheck =
    {
      command,
      name,
      nativeBuildInputs,
    }:
    pkgs.runCommandLocal "phpstan-lost-in-translation-${name}" { inherit nativeBuildInputs; } ''
      export HOME="$TMPDIR/home"
      export XDG_CACHE_HOME="$TMPDIR/cache"
      mkdir -p "$HOME" "$XDG_CACHE_HOME"
      cd ${src}
      ${command}
      touch "$out"
    '';

  phpunitMatrix = [
    {
      phpName = "php81";
      laravel = "9";
      repository = repositories.laravel9;
      composerLock = ./composer/laravel-9.lock;
    }
    {
      phpName = "php82";
      laravel = "9";
      repository = repositories.laravel9;
      composerLock = ./composer/laravel-9.lock;
    }
    {
      phpName = "php81";
      laravel = "10";
      repository = repositories.root;
      composerLock = ../composer.lock;
    }
    {
      phpName = "php82";
      laravel = "10";
      repository = repositories.root;
      composerLock = ../composer.lock;
    }
    {
      phpName = "php83";
      laravel = "10";
      repository = repositories.root;
      composerLock = ../composer.lock;
    }
    {
      phpName = "php82";
      laravel = "11";
      repository = repositories.laravel11;
      composerLock = ./composer/laravel-11.lock;
    }
    {
      phpName = "php83";
      laravel = "11";
      repository = repositories.laravel11;
      composerLock = ./composer/laravel-11.lock;
    }
    {
      phpName = "php84";
      laravel = "11";
      repository = repositories.laravel11;
      composerLock = ./composer/laravel-11.lock;
    }
    {
      phpName = "php85";
      laravel = "11";
      repository = repositories.laravel11;
      composerLock = ./composer/laravel-11.lock;
    }
    {
      phpName = "php82";
      laravel = "12";
      repository = repositories.laravel12;
      composerLock = ./composer/laravel-12.lock;
    }
    {
      phpName = "php83";
      laravel = "12";
      repository = repositories.laravel12;
      composerLock = ./composer/laravel-12.lock;
    }
    {
      phpName = "php84";
      laravel = "12";
      repository = repositories.laravel12;
      composerLock = ./composer/laravel-12.lock;
    }
    {
      phpName = "php85";
      laravel = "12";
      repository = repositories.laravel12;
      composerLock = ./composer/laravel-12.lock;
    }
    {
      phpName = "php83";
      laravel = "13";
      repository = repositories.laravel13;
      composerLock = ./composer/laravel-13.lock;
    }
    {
      phpName = "php84";
      laravel = "13";
      repository = repositories.laravel13;
      composerLock = ./composer/laravel-13.lock;
    }
    {
      phpName = "php85";
      laravel = "13";
      repository = repositories.laravel13;
      composerLock = ./composer/laravel-13.lock;
    }
  ];

  phpunitChecks = lib.listToAttrs (
    map (
      entry:
      lib.nameValuePair "phpunit-${entry.phpName}-laravel${entry.laravel}" (mkPhpCheck {
        name = "phpunit-${entry.phpName}-laravel${entry.laravel}";
        inherit (entry) composerLock repository;
        php = php.${entry.phpName};
        command = ''
          php ./vendor/bin/phpunit --no-coverage --colors=never
        '';
      })
    ) phpunitMatrix
  );

  prepareToolSource = ''
    packagePath="vendor/jbboehr/phpstan-lost-in-translation"
    if [ -L "$packagePath" ]; then
      unlink "$packagePath"
    elif [ -d "$packagePath" ]; then
      chmod -R u+w "$packagePath"
      find "$packagePath" -mindepth 1 -delete
      rmdir "$packagePath"
    fi
    ln -s "$(realpath ../..)" "$packagePath"
  '';

  mutation = mkPhpCheck {
    name = "mutation";
    php = php84WithPcov;
    prepare = ''
      # Infection executes Composer's PHPUnit proxy directly. Give that proxy
      # a Nix-store interpreter while leaving fixture shebangs untouched.
      patchShebangs vendor/bin
    '';
    command = ''
      ${php84WithPcov}/bin/php ${infectionPhar} --no-progress
    '';
    result = ''
      mkdir -p "$out"
      cp infection.log infection-summary.log "$out/"
    '';
  };
in
{
  inherit mutation repositories;

  checks = phpunitChecks // {
    composer-validate = mkSimpleCheck {
      name = "composer-validate";
      nativeBuildInputs = [
        php.php81
        php.php81.packages.composer
      ];
      command = ''
        composer validate --strict
        composer --working-dir=tools/eris validate --strict
        composer --working-dir=tools/akashi validate --strict
      '';
    };

    php-lint = mkSimpleCheck {
      name = "php-lint";
      nativeBuildInputs = [ php.php81 ];
      command = ''
        find src tests tools e2e -type f -name '*.php' \
          ! -path 'tests/Rule/lang-warn/zh/messages.php' \
          -print0 \
          | sort -z \
          | xargs -0 -n1 php -l
      '';
    };

    php-cs-fixer = mkPhpCheck {
      name = "php-cs-fixer";
      php = php.php81;
      command = ''
        php ./vendor/bin/php-cs-fixer check --diff --show-progress=none --using-cache=no
      '';
    };

    phpcs = mkPhpCheck {
      name = "phpcs";
      php = php.php81;
      command = ''
        php ./vendor/bin/phpcs
      '';
    };

    phpstan = mkPhpCheck {
      name = "phpstan";
      php = php.php81;
      command = ''
        php ./vendor/bin/phpstan analyse --error-format=raw --no-progress
      '';
    };

    lowest-phpunit = mkPhpCheck {
      name = "lowest-phpunit";
      php = php.php81;
      repository = repositories.lowest;
      composerLock = ./composer/lowest.lock;
      command = ''
        php ./vendor/bin/phpunit --no-coverage --colors=never
      '';
    };

    lowest-phpstan = mkPhpCheck {
      name = "lowest-phpstan";
      php = php.php81;
      repository = repositories.lowest;
      composerLock = ./composer/lowest.lock;
      command = ''
        php ./vendor/bin/phpstan analyse --error-format=raw --no-progress
      '';
    };

    lowest-runtime-smoke = mkPhpCheck {
      name = "lowest-runtime-smoke";
      php = php.php81;
      repository = repositories.lowest;
      composerLock = ./composer/lowest.lock;
      command = ''
        php tests/runtime-smoke/run.php
      '';
    };

    lowest-e2e = mkPhpCheck {
      name = "lowest-e2e";
      php = php.php81;
      repository = repositories.lowest;
      composerLock = ./composer/lowest.lock;
      command = ''
        set +o pipefail
        php ./vendor/bin/phpstan analyse --configuration=e2e/phpstan-e2e.neon --error-format=json \
          | php ./e2e/test-runner
        set -o pipefail
      '';
    };

    runtime-smoke = mkPhpCheck {
      name = "runtime-smoke";
      php = php.php81;
      composerNoDev = true;
      command = ''
        php tests/runtime-smoke/run.php
      '';
    };

    package-consumer = mkPhpCheck {
      name = "package-consumer";
      php = php.php81;
      command = ''
        repositoryManifest="$TMPDIR/composer-repository"
        mkdir -p "$repositoryManifest"
        composer build-local-repo --only-manifest "${repositories.root}" "$repositoryManifest"
        export COMPOSER_DISABLE_NETWORK=1
        export PACKAGE_CHECK_COMPOSER_REPOSITORY="$repositoryManifest"
        php tools/check-package.php
      '';
    };

    e2e = mkPhpCheck {
      name = "e2e";
      php = php.php81;
      command = ''
        set +o pipefail
        php ./vendor/bin/phpstan analyse --configuration=e2e/phpstan-e2e.neon --error-format=json \
          | php ./e2e/test-runner
        set -o pipefail
      '';
    };

    benchmark-smoke = mkPhpCheck {
      name = "benchmark-smoke";
      php = php.php81;
      command = ''
        php ./vendor/bin/phpbench run --iterations=1 --revs=1 --warmup=1 --progress=none
      '';
    };

    branch-coverage = mkPhpCheck {
      name = "branch-coverage";
      php = php84WithXdebug;
      command = ''
        XDEBUG_MODE=coverage php ./vendor/bin/phpunit \
          --coverage-filter src \
          --coverage-cobertura coverage.xml \
          --coverage-text \
          --path-coverage \
          --colors=never
      '';
      result = ''
        mkdir -p "$out"
        cp coverage.xml "$out/"
      '';
    };

    eris = mkPhpCheck {
      name = "eris";
      php = php.php81;
      source = src;
      repository = repositories.eris;
      composerLock = ../tools/eris/composer.lock;
      postUnpack = ''
        sourceRoot="$sourceRoot/tools/eris"
      '';
      prepare = prepareToolSource;
      command = ''
        php ./vendor/bin/phpunit --configuration phpunit.xml.dist --colors=never
      '';
    };

    documentation-phpstan = mkPhpCheck {
      name = "documentation-phpstan";
      php = php.php82;
      source = src;
      repository = repositories.akashi;
      composerLock = ../tools/akashi/composer.lock;
      postUnpack = ''
        sourceRoot="$sourceRoot/tools/akashi"
      '';
      prepare = prepareToolSource;
      command = ''
        php ./vendor/bin/phpstan analyse --configuration phpstan.neon.dist --no-progress --error-format=raw
      '';
    };

    documentation-phpunit = mkPhpCheck {
      name = "documentation-phpunit";
      php = php.php82;
      source = src;
      repository = repositories.akashi;
      composerLock = ../tools/akashi/composer.lock;
      postUnpack = ''
        sourceRoot="$sourceRoot/tools/akashi"
      '';
      prepare = prepareToolSource;
      command = ''
        php ./vendor/bin/phpunit --configuration phpunit.xml.dist --colors=never
      '';
    };
  };
}
