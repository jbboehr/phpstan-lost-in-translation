# Copyright (c) anno Domini nostri Jesu Christi MMXXV John Boehr & contributors
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU Affero General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU Affero General Public License for more details.
#
# You should have received a copy of the GNU Affero General Public License
# along with this program.  If not, see <http://www.gnu.org/licenses/>.
{
  description = "jbboehr/phpstan-lost-in-translation";
  inputs = {
    akashi = {
      url = "github:jbboehr/akashi.php/225cc33f61d5779791112fb6c3b0f473e9c8e5ae";
      inputs.flake-utils.follows = "flake-utils";
      inputs.nixpkgs.follows = "nixpkgs";
      inputs.pre-commit-hooks.follows = "git-hooks";
    };
    nixpkgs.url = "github:nixos/nixpkgs/nixos-26.05";
    systems.url = "github:nix-systems/default";
    flake-utils = {
      url = "github:numtide/flake-utils";
      inputs.systems.follows = "systems";
    };
    phps.url = "github:fossar/nix-phps";
    git-hooks = {
      url = "github:cachix/git-hooks.nix";
      inputs.nixpkgs.follows = "nixpkgs";
    };
  };

  outputs =
    {
      self,
      akashi,
      nixpkgs,
      systems,
      flake-utils,
      phps,
      git-hooks,
    }:
    flake-utils.lib.eachDefaultSystem (
      system:
      let
        agentBadge =
          if pkgs.stdenv.isLinux then
            akashi.packages.${system}.agent-badge
          else
            akashi.packages.${system}.agent-badge-unwrapped;
        buildEnv =
          {
            php,
            withPcov ? true,
          }:
          php.buildEnv {
            extraConfig = "memory_limit = 2G";
            extensions =
              {
                enabled,
                all,
              }:
              enabled ++ (pkgs.lib.optionals withPcov [ all.pcov ]);
          };
        pkgs = nixpkgs.legacyPackages.${system};
        src = ./.;

        pre-commit-check = git-hooks.lib.${system}.run {
          inherit src;
          hooks = {
            actionlint.enable = true;
            nixfmt.enable = true;
            shellcheck.enable = true;
          };
        };

        makeShell =
          {
            php,
            withPcov ? true,
            withInfection ? false,
          }:
          let
            php' = buildEnv { inherit php withPcov; };
            infection = pkgs.writeShellScriptBin "infection" ''
              exec ${php'}/bin/php ${infectionPhar} "$@"
            '';
          in
          pkgs.mkShell {
            packages =
              pre-commit-check.enabledPackages
              ++ [
                agentBadge
                php'
                php'.packages.composer
              ]
              ++ pkgs.lib.optional withInfection infection;
            shellHook = ''
              ${pre-commit-check.shellHook}
              export PATH="$PWD/vendor/bin:$PATH"
              export PHPUNIT_WITH_PCOV="$PHP_WITH_PCOV -d memory_limit=512M -d pcov.directory=$PWD -dpcov.exclude="~vendor~" ./vendor/bin/phpunit"
            '';
          };
        infectionPhar = pkgs.fetchurl {
          url = "https://github.com/infection/infection/releases/download/0.34.2/infection.phar";
          hash = "sha256-roO8UVFXmBfDpiF+s97OuGoWmjmROSZFNCGLcjMg2j0=";
        };
      in
      rec {
        checks = {
          inherit pre-commit-check;
        };

        devShells = rec {
          php81 = makeShell { php = phps.packages.${system}.php81; };
          php82 = makeShell { php = pkgs.php82; };
          php83 = makeShell { php = pkgs.php83; };
          php84 = makeShell { php = pkgs.php84; };
          php85 = makeShell { php = pkgs.php85; };
          mutation = makeShell {
            php = pkgs.php84;
            withInfection = true;
          };
          default = php81;
        };

        formatter = pkgs.nixfmt-tree;
      }
    );
}
