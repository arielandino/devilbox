import re

with open('.devilbox/www/htdocs/vhosts.php', 'r') as f:
    content = f.read()

replacement = "autostart/<?php $vHosts = loadClass('Httpd')->getVirtualHosts(); ?>"
				<?php if ($vHosts): ?>
					<?php
					$env_groups_str = loadClass('Helper')->getEnv('VHOST_GROUPS');
					$vhost_groups = [];
					if ($env_groups_str) {
						$pairs = explode(',', $env_groups_str);
						foreach ($pairs as $pair) {
							$parts = explode('=', $pair, 2);
							if (count($parts) == 2) {
								$vhost_groups[trim($parts[0])] = trim($parts[1]);
							}
						}
					}
					$grouped_vhosts = [];
					foreach ($vhost_groups as $prefix => $label) {
						$grouped_vhosts[$label] = [];
					}
					$grouped_vhosts['Other'] = [];

					foreach ($vHosts as $vHost) {
						$matched = false;
						foreach ($vhost_groups as $prefix => $label) {
							if (strpos($vHost['name'], $prefix) === 0) {
								$grouped_vhosts[$label][] = $vHost;
								$matched = true;
								break;
							}
						}
						if (.envmatched) {
							$grouped_vhosts['Other'][] = $vHost;
						}
					}
					?>

					<?php foreach ($grouped_vhosts as $group_label => $hosts): ?>
						<?php if (empty($hosts)) continue; ?>
						<h3 data-toggle="collapse" data-target="#group-<?php echo md5($group_label); ?>" style="cursor: pointer; margin-top: 20px; font-size: 1.4rem;">
							<i class="fa fa-folder-open-o" aria-hidden="true"></i> <?php echo htmlspecialchars($group_label); ?>
						</h3>
						<div id="group-<?php echo md5($group_label); ?>" class="collapse show">
						<table class="table table-striped">
							<thead class="thead-inverse">
								<tr>
									<th style="width:60px;">Logo</th>
									<th style="width:260px;">URL</th>
									<th>DocumentRoot</th>
									<th>Backend</th>
									<th>Config</th>
									<th style="width:60px;">Valid</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($hosts as $vHost): ?>
									<tr>
										<td class="text-xs-center">
											<?php if ($logo = getProjectLogo($vHost['name'])): ?>
												<img src="<?php echo $logo; ?>" style="max-height:34px; max-width: 34px; vertical-align: middle; border-radius: 2px;" />
											<?php else: ?>
												<i class="fa fa-globe" aria-hidden="true" style="font-size: 34px; vertical-align: middle; color: #adb5bd;"></i>
											<?php endif; ?>
										</td>
										<td id="href-<?php echo $vHost['name']; ?>">
											<?php echo htmlspecialchars($vHost['name']); ?>
										</td>
										<td><?php echo loadClass('Helper')->getEnv('HOST_PATH_HTTPD_DATADIR'); ?>/<?php echo $vHost['name']; ?>/<?php echo loadClass('Helper')->getEnv('HTTPD_DOCROOT_DIR'); ?></td>
										<td><?php echo loadClass('Httpd')->getVhostBackend($vHost['name']); ?></td>
										<td>
"""

replacement2 = """
										</td>
										<td class="text-xs-center text-xs-small" id="valid-<?php echo $vHost['name']; ?>"></td>
									</tr>
									<input type="hidden" name="vhost[]" class="vhost" value="<?php echo $vHost['name']; ?>" />
								<?php endforeach; ?>
							</tbody>
						</table>
						</div>
					<?php endforeach; ?>
				<?php else: ?>"""

pattern1 = r"\<\?php \$vHosts = loadClass\('Httpd'\)-\>getVirtualHosts\(\); \?\>[\s\S]*?\<td\>\s*\<\?php \$id_vhost_httpd = str_replace\("
pattern2 = r"\<\?php endif; \?\>[\s\S]*?\<\/td\>[\s\S]*?\<td class=\"text-xs-center text-xs-small\" id=\"valid-\<\?php echo \$vHost\['name'\]; \?\>\"\>\<\/td\>[\s\S]*?\<td id=\"href-\<\?php echo \$vHost\['name'\]; \?\>\"\>\<\/td\>[\s\S]*?\<\/tr\>[\s\S]*?\<input type=\"hidden\" name=\"vhost\[\]\" class=\"vhost\" value=\"\<\?php echo \$vHost\['name'\]; \?\>\" \/\>[\s\S]*?\<\?php endforeach; \?\>[\s\S]*?\<\/tbody\>[\s\S]*?\<\/table\>[\s\S]*?\<\?php else: \?\>"

content = re.sub(pattern1, replacement + "\t\t\t\t\t\t\t\t\t\t<?php $id_vhost_httpd = str_replace(", content, 1)
content = re.sub(pattern2, "<?php endif; ?>\n" + replacement2, content, 1)

with open('.devilbox/www/htdocs/vhosts.php', 'w') as f:
    f.write(content)
