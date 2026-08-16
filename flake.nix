# Copyright (c) anno Domini nostri Jesu Christi MMXXV John Boehr & contributors
#
# SPDX-License-Identifier: AGPL-3.0-only WITH romic-exception
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU Affero General Public License version 3,
# as published by the Free Software Foundation, together with the Romic
# Exception (an additional permission under section 7 of that license).
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU Affero General Public License for more details.
#
# You should have received a copy of the GNU Affero General Public License
# and the Romic Exception along with this program.  If not, see
# <http://www.gnu.org/licenses/> and docs/LICENSE_EXCEPTION.md.
{
  description = "jbboehr/phpstan-lost-in-translation";
  inputs = {
    agent-badge = {
      url = "github:jbboehr/agent-badge.ts/4e8f892f443245cf9a32583034d3a155e9fdcb48";
      inputs.flake-utils.follows = "flake-utils";
      inputs.nixpkgs.follows = "nixpkgs";
    };
    nixpkgs.url = "github:nixos/nixpkgs/nixos-26.05";
    systems.url = "github:nix-systems/default";
    flake-utils = {
      url = "github:numtide/flake-utils";
      inputs.systems.follows = "systems";
    };
    phps.url = "github:fossar/nix-phps";
    perfidious = {
      url = "github:jbboehr/php-perfidious";
      inputs.flake-utils.follows = "flake-utils";
      inputs.nix-github-actions.follows = "nix-github-actions";
      inputs.nix-phps.follows = "phps";
      inputs.nixpkgs.follows = "nixpkgs";
      inputs.pre-commit-hooks.follows = "git-hooks";
      inputs.systems.follows = "systems";
    };
    git-hooks = {
      url = "github:cachix/git-hooks.nix";
      inputs.nixpkgs.follows = "nixpkgs";
    };
    nix-github-actions = {
      url = "github:nix-community/nix-github-actions";
      inputs.nixpkgs.follows = "nixpkgs";
    };
  };

  outputs =
    {
      self,
      agent-badge,
      nixpkgs,
      systems,
      flake-utils,
      phps,
      perfidious,
      git-hooks,
      nix-github-actions,
    }:
    let
      perSystem = flake-utils.lib.eachDefaultSystem (
        system:
        let
          agentBadge =
            if pkgs.stdenv.isLinux then
              agent-badge.packages.${system}.agent-badge
            else
              agent-badge.packages.${system}.agent-badge-unwrapped;
          buildEnv =
            {
              extraExtensions ? [ ],
              php,
              withPcov ? true,
              withXdebug ? false,
            }:
            assert !(withPcov && withXdebug);
            php.buildEnv {
              extraConfig = "memory_limit = 2G";
              extensions =
                {
                  enabled,
                  all,
                }:
                enabled
                ++ extraExtensions
                ++ (pkgs.lib.optionals withPcov [ all.pcov ])
                ++ (pkgs.lib.optionals withXdebug [ all.xdebug ]);
            };
          pkgs = nixpkgs.legacyPackages.${system};
          src = pkgs.lib.cleanSourceWith {
            src = ./.;
            filter =
              path: type:
              let
                relativePath = pkgs.lib.removePrefix "${toString ./.}/" (toString path);
                topLevel = builtins.head (pkgs.lib.splitString "/" relativePath);
              in
              !builtins.elem topLevel [
                ".direnv"
                ".git"
                "build"
                "coverage"
                "result"
                "secrets"
                "vendor"
              ]
              && !pkgs.lib.hasPrefix "result" topLevel
              && !pkgs.lib.hasPrefix "tmp" topLevel
              && !pkgs.lib.hasPrefix ".github/agent-badge/cache/" relativePath
              && !pkgs.lib.hasPrefix ".github/agent-badge/logs/" relativePath
              && !builtins.elem relativePath [
                ".github/agent-badge/cache"
                ".github/agent-badge/logs"
                ".github/agent-badge/state.json"
                "tools/akashi/vendor"
                "tools/eris/vendor"
              ]
              && !pkgs.lib.hasPrefix "tools/akashi/vendor/" relativePath
              && !pkgs.lib.hasPrefix "tools/eris/vendor/" relativePath
              && !pkgs.lib.hasSuffix ".log" relativePath
              && !builtins.elem relativePath [
                ".php-cs-fixer.cache"
                ".phpunit.result.cache"
                "clover.xml"
              ];
          };

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
              withMdbook ? false,
              extraExtensions ? [ ],
            }:
            let
              php' = buildEnv { inherit extraExtensions php withPcov; };
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
                ++ pkgs.lib.optional withInfection infection
                ++ pkgs.lib.optional withMdbook pkgs.mdbook;
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
          perfidiousEnabled = pkgs.stdenv.isLinux && pkgs.stdenv.hostPlatform.isx86_64;
          perfidiousExtension = if perfidiousEnabled then perfidious.packages.${system}.php84-gcc else null;
          php84WithPerfidious = buildEnv {
            extraExtensions = pkgs.lib.optional perfidiousEnabled perfidiousExtension;
            php = pkgs.php84;
            withPcov = false;
          };
          validation = import ./nix/validation.nix {
            inherit
              buildEnv
              infectionPhar
              perfidiousEnabled
              php84WithPerfidious
              pkgs
              src
              ;
            php81 = phps.packages.${system}.php81;
            inherit (pkgs)
              php82
              php83
              php84
              php85
              ;
          };
          validationChecks = validation.checks // {
            inherit pre-commit-check;
          };
          githubCheckMatrix = nix-github-actions.lib.mkGithubMatrix {
            checks = {
              ${system} = validationChecks;
            };
            attrPrefix = "checks";
          };
          githubMutationMatrix = nix-github-actions.lib.mkGithubMatrix {
            checks = {
              ${system} = {
                mutation = validation.mutation;
              };
            };
            attrPrefix = "packages";
          };
          githubMatrix = {
            include = githubCheckMatrix.matrix.include ++ githubMutationMatrix.matrix.include;
          };
        in
        rec {
          checks = validationChecks;

          packages = {
            composer-dependencies = validation.repositories.root;
            mutation = validation.mutation;
            github-actions-matrix =
              pkgs.runCommandLocal "phpstan-lost-in-translation-github-actions-matrix"
                {
                  passthru = {
                    matrix = githubMatrix;
                  };
                }
                ''
                  mkdir -p "$out"
                  printf '%s\n' '${builtins.toJSON githubMatrix}' > "$out/matrix.json"
                '';
          }
          // pkgs.lib.optionalAttrs perfidiousEnabled {
            benchmark-perfidious = validation.perfidiousBenchmark;
          };

          devShells = rec {
            php81 = makeShell { php = phps.packages.${system}.php81; };
            php82 = makeShell { php = pkgs.php82; };
            php83 = makeShell { php = pkgs.php83; };
            php84 = makeShell { php = pkgs.php84; };
            php85 = makeShell { php = pkgs.php85; };
            documentation = makeShell {
              php = pkgs.php82;
              withMdbook = true;
            };
            mutation = makeShell {
              php = pkgs.php84;
              withInfection = true;
            };
            default = php81;
          }
          // pkgs.lib.optionalAttrs perfidiousEnabled {
            benchmark = makeShell {
              extraExtensions = [ perfidiousExtension ];
              php = pkgs.php84;
              withPcov = false;
            };
          };

          formatter = pkgs.nixfmt-tree;
        }
      );
    in
    perSystem;
}
