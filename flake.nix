{
  description = "jbboehr/phpstan-lost-in-translation";
  inputs = {
    nixpkgs.url = "github:nixos/nixpkgs/nixos-25.05";
    systems.url = "github:nix-systems/default";
    flake-utils = {
      url = "github:numtide/flake-utils";
      inputs.systems.follows = "systems";
    };
    pre-commit-hooks = {
      url = "github:cachix/pre-commit-hooks.nix";
      inputs.nixpkgs.follows = "nixpkgs";
      inputs.gitignore.follows = "gitignore";
    };
    gitignore = {
      url = "github:hercules-ci/gitignore.nix";
      inputs.nixpkgs.follows = "nixpkgs";
    };
  };

  outputs = {
    self,
    nixpkgs,
    systems,
    flake-utils,
    pre-commit-hooks,
    gitignore,
  }:
    flake-utils.lib.eachDefaultSystem (system: let
      buildEnv = {
        php,
        withPcov ? true,
      }:
        php.buildEnv {
          extraConfig = "memory_limit = 2G";
          extensions = {
            enabled,
            all,
          }:
            enabled ++ (pkgs.lib.optionals withPcov [all.pcov]);
        };
      pkgs = nixpkgs.legacyPackages.${system};
      src = gitignore.lib.gitignoreSource ./.;

      pre-commit-check = pre-commit-hooks.lib.${system}.run {
        inherit src;
        hooks = {
          actionlint.enable = true;
          alejandra.enable = true;
          alejandra.excludes = ["\/vendor\/"];
          # https://github.com/cachix/pre-commit-hooks.nix/pull/344
          #phpcs.enable = true;
          shellcheck.enable = true;
        };
      };

      makeShell = {
        php,
        withPcov ? true,
      }: let
        php' = buildEnv {inherit php withPcov;};
      in
        pkgs.mkShell {
          buildInputs = with pkgs; [
            actionlint
            mdl
            nixpkgs-fmt
            php'
            php'.packages.composer
            pre-commit
          ];
          shellHook = ''
            ${pre-commit-check.shellHook}
            export PATH="$PWD/vendor/bin:$PATH"
            export PHPUNIT_WITH_PCOV="$PHP_WITH_PCOV -d memory_limit=512M -d pcov.directory=$PWD -dpcov.exclude="~vendor~" ./vendor/bin/phpunit"
          '';
        };
    in rec {
      checks = {
        inherit pre-commit-check;
      };

      devShells = rec {
        php81 = makeShell {php = pkgs.php81;};
        php82 = makeShell {php = pkgs.php82;};
        php83 = makeShell {php = pkgs.php83;};
        php84 = makeShell {php = pkgs.php84;};
        default = php81;
      };

      formatter = pkgs.alejandra;
    });
}
