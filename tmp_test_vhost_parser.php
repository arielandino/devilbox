<?php
$vhost_to_group_map = [];
$vhost_alias_map = [];
$group_labels = [];
$yaml_file = 'vhost-groups.yml';

if (file_exists($yaml_file)) {
    if (function_exists('yaml_parse_file')) {
        $yaml_data = yaml_parse_file($yaml_file);
        if (is_array($yaml_data)) {
            foreach ($yaml_data as $group => $sites) {
                if (is_array($sites)) {
                    $group_labels[] = $group;
                    foreach ($sites as $site) {
                        if (is_scalar($site)) {
                            $vhost_to_group_map[(string)$site] = $group;
                        } elseif (is_array($site) || is_object($site)) {
                            $site_data = (array)$site;
                            foreach ($site_data as $source => $aliases) {
                                if (!is_scalar($source)) {
                                    continue;
                                }
                                $source_key = (string)$source;
                                $vhost_to_group_map[$source_key] = $group;
                                foreach ((array)$aliases as $alias) {
                                    if (!is_scalar($alias)) {
                                        continue;
                                    }
                                    $vhost_alias_map[(string)$alias] = $source_key;
                                }
                            }
                        }
                    }
                }
            }
        }
    } else {
        $lines = file($yaml_file);
        $current_group = '';
        $current_alias_base = null;
        foreach ($lines as $line) {
            $line = rtrim($line);
            $trimmed = trim($line);
            if (empty($trimmed) || strpos($trimmed, '#') === 0) continue;

            if (preg_match('/^([^ -][^:]*):\s*$/', $trimmed, $matches)) {
                $current_group = trim($matches[1]);
                $group_labels[] = $current_group;
                $current_alias_base = null;
                continue;
            }

            if (!$current_group) continue;

            if (preg_match('/^\s*-\s*([^:]+?):\s*$/', $line, $matches)) {
                $current_alias_base = trim($matches[1]);
                $vhost_to_group_map[$current_alias_base] = $current_group;
                continue;
            }

            if ($current_alias_base && preg_match('/^\s+[-*]\s*(.+)$/', $line, $matches)) {
                $alias = trim($matches[1]);
                if ($alias !== '') {
                    $vhost_alias_map[$alias] = $current_alias_base;
                }
                continue;
            }

            if (preg_match('/^\s*-\s*(.+)/', $line, $matches)) {
                $site = trim($matches[1]);
                if ($site !== '') {
                    $vhost_to_group_map[$site] = $current_group;
                }
                $current_alias_base = null;
            }
        }
    }
}

var_dump($vhost_to_group_map);
var_dump($vhost_alias_map);
var_dump($group_labels);
