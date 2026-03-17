#!/usr/bin/env python3
"""Apply alias entries (from vhost-groups.yml) as symlinked project dirs.

This script reads the vhost-groups.yml file and creates symlinks in
HOST_PATH_HTTPD_DATADIR for alias hostnames so they resolve to the
same document root as the canonical project.

Example in vhost-groups.yml:

Produccion:
  - pa_resto
  - pa_resto:
      - admin.pa_resto
      - api.pa_resto

This creates:
  data/www/admin.pa_resto -> data/www/pa_resto
  data/www/api.pa_resto -> data/www/pa_resto

It can be run before starting Devilbox to keep alias configuration in sync.
"""

import os
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent
VHOST_GROUPS_YML = ROOT / 'vhost-groups.yml'
DEFAULT_DATADIR = ROOT / 'data' / 'www'

# parse environment .env keys if available
ENV_FILE = ROOT / '.env'

def read_env(path):
    env = {}
    if not path.is_file():
        return env
    with path.open('r', encoding='utf-8') as f:
        for line in f:
            line = line.strip()
            if not line or line.startswith('#'):
                continue
            if '=' not in line:
                continue
            k, v = line.split('=', 1)
            env[k.strip()] = v.strip().strip('"\'')
    return env


def parse_vhost_groups(path):
    if not path.is_file():
        return {}

    root_aliases = {}
    current_group = None
    current_base = None

    for line in path.read_text(encoding='utf-8').splitlines():
        stripped = line.strip()
        if not stripped or stripped.startswith('#'):
            continue

        group_match = re.match(r'^([A-Za-z0-9_\- ]+):\s*$', stripped)
        if group_match and line.startswith(group_match.group(1)):
            current_group = group_match.group(1).strip()
            current_base = None
            continue

        alias_item = re.match(r'^\s{4,}[-*]\s*(.+)$', line)
        if alias_item and current_base:
            # Nested alias list item for mapping value.
            alias = alias_item.group(1).strip()
            if alias:
                root_aliases[current_base] = root_aliases.get(current_base, []) + [alias]
            continue

        mapping_match = re.match(r'^\s*-\s*([^:\[]+?):\s*$', stripped)
        if mapping_match:
            current_base = mapping_match.group(1).strip()
            continue

        item_match = re.match(r'^\s*-\s*([^:\[]+?)\s*(?:\:\s*\[([^\]]*)\])?\s*$', stripped)
        if item_match:
            base = item_match.group(1).strip()
            list_inline = item_match.group(2)
            if list_inline is not None:
                aliases = [x.strip() for x in list_inline.split(',') if x.strip()]
                if aliases:
                    root_aliases[base] = root_aliases.get(base, []) + aliases
                current_base = None
                continue
            # mapping start
            if stripped.endswith(':'):
                current_base = base
                continue
            # plain project value
            if base not in root_aliases:
                root_aliases.setdefault(base, [])
            current_base = None
            continue

        # any other line resets current alias base
        current_base = None

    # Remove entries where no explicit alias list is set
    root_aliases = {k: [a for a in v if a] for k, v in root_aliases.items() if v}
    return root_aliases


def main():
    env = read_env(ENV_FILE)
    datadir = Path(env.get('HOST_PATH_HTTPD_DATADIR', DEFAULT_DATADIR)).expanduser().resolve()
    groups = parse_vhost_groups(VHOST_GROUPS_YML)

    if not datadir.exists():
        print(f"[ERROR] host path data dir does not exist: {datadir}", file=sys.stderr)
        return 1

    if not groups:
        print("No alias entries found in vhost-groups.yml. Nothing to do.")
        return 0

    docroot_dir = env.get('HTTPD_DOCROOT_DIR', 'public_html')

    for source, aliases in groups.items():
        source_dir = datadir / source
        source_docroot = source_dir / docroot_dir
        if not source_dir.exists():
            print(f"[WARNING] canonical project directory does not exist: {source_dir}")
            continue
        
        # Check if source has the docroot
        if not source_docroot.exists():
            print(f"[WARNING] source docroot not found: {source_docroot}")
            # Fallback to linking the whole project if docroot doesn't exist?
            # Or just link the project dir as base.
            source_target = source_dir
        else:
            source_target = source_docroot

        for alias in aliases:
            alias_path = datadir / alias
            
            # If alias is a symlink to the whole project (old behavior), remove it to upgrade
            if alias_path.is_symlink() and (alias_path.parent / os.readlink(alias_path)).resolve() == source_dir.resolve():
                print(f"Removing old project-level symlink for {alias}")
                alias_path.unlink()

            if not alias_path.exists():
                alias_path.mkdir(parents=True, exist_ok=True)
                print(f"Created alias directory: {alias_path}")

            alias_docroot = alias_path / docroot_dir
            link_target = os.path.relpath(source_target, start=alias_docroot.parent)

            if alias_docroot.exists() or alias_docroot.is_symlink():
                if alias_docroot.is_symlink():
                    current_target = os.readlink(alias_docroot)
                    if os.path.isabs(current_target):
                        resolved_current = Path(current_target).resolve()
                    else:
                        resolved_current = (alias_docroot.parent / current_target).resolve()

                    if resolved_current == source_target.resolve():
                        print(f"Alias docroot exists and is correct: {alias_docroot} -> {current_target}")
                        continue
                    print(f"Updating alias docroot symlink: {alias_docroot} (was -> {current_target})")
                    alias_docroot.unlink()
                else:
                    print(f"[SKIP] Alias docroot exists but is a real directory: {alias_docroot}")
                    continue

            alias_docroot.symlink_to(link_target)
            print(f"Created alias docroot: {alias_docroot} -> {link_target}")
            changed = True

    if not changed:
        print("No changes needed; alias workspace already in sync.")

    return 0


if __name__ == '__main__':
    sys.exit(main())
