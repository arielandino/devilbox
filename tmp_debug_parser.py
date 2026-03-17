import re
text = '''Produccion:
  - pa_resto
  - pa_resto:
      - admin.pa_resto
'''
lines = text.splitlines()
root_aliases = {}
current_group = None
current_base = None
for line in lines:
    stripped = line.strip()
    print('LINE>', repr(line), 'STRIPPED>', repr(stripped), 'cg>', current_group, 'cb>', current_base)
    if not stripped or stripped.startswith('#'):
        continue
    group_match = re.match(r'^([A-Za-z0-9_\- ]+):\s*$', stripped)
    if group_match and line.startswith(group_match.group(1)):
        current_group = group_match.group(1).strip(); current_base = None
        print('  group', current_group)
        continue
    if current_base is not None:
        nested_alias = re.match(r'^\s+[-*]\s*(.+)$', line)
        print('  nested_alias', nested_alias)
        if nested_alias:
            alias = nested_alias.group(1).strip()
            print('    alias', alias)
            if alias:
                root_aliases[current_base] = root_aliases.get(current_base, []) + [alias]
            continue
    item_match = re.match(r'^\s*-\s*([^:\[]+?)\s*(?:\:\s*\[([^\]]*)\])?\s*$', stripped)
    print('  item_match', item_match)
    if item_match:
        base = item_match.group(1).strip(); list_inline = item_match.group(2)
        print('    base', base, 'list_inline', list_inline)
        if list_inline is not None:
            aliases = [x.strip() for x in list_inline.split(',') if x.strip()]
            print('    list aliases', aliases)
            if aliases:
                root_aliases[base] = root_aliases.get(base, []) + aliases
            current_base = None
            continue
        if stripped.endswith(':'):
            current_base = base
            print('    set current_base', current_base)
            continue
        if base not in root_aliases:
            root_aliases.setdefault(base, [])
        current_base = None
        continue
    current_base = None
print(root_aliases)
