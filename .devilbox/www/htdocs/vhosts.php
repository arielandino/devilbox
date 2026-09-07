<?php require '../config.php'; ?>
<?php loadClass('Helper')->authPage(); ?>
<?php
/**
 * Get the logo of a project as a base64 string
 */
function getProjectLogo($vhost)
{
	$htdocs = loadClass('Helper')->getEnv('HTTPD_DOCROOT_DIR');
	$search_dirs = [
		'/shared/httpd/' . $vhost,
		'/shared/httpd/' . $vhost . '/' . $htdocs,
	];

	$env_logos = loadClass('Helper')->getEnv('DEVILBOX_PROJECT_LOGOS');
	$search_files = $env_logos ? explode(',', $env_logos) : ['logo.png', 'logo.jpg', 'logo.jpeg', 'logo.svg', 'apple-touch-icon.png', 'favicon.ico'];

	foreach ($search_dirs as $dir) {
		if (!is_dir($dir))
			continue;
		foreach ($search_files as $file) {
			$full_path = $dir . DIRECTORY_SEPARATOR . $file;
			if (is_file($full_path)) {
				$data = file_get_contents($full_path);
				$finfo = new finfo(FILEINFO_MIME_TYPE);
				$mime = $finfo->buffer($data);
				$base64 = 'data:' . $mime . ';base64,' . base64_encode($data);
				return $base64;
			}
		}
	}
	return null;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
	<?php echo loadClass('Html')->getHead(true); ?>
</head>

<body>
	<?php echo loadClass('Html')->getNavbar(); ?>

	<div class="container">

		<div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap: 1rem; margin-bottom: 1.5rem;">
			<h1 style="margin:0;">Virtual Host 2.0</h1>
			<div style="flex:1 1 320px; max-width:520px; min-width:240px;">
				<input type="text" id="vhostFilter" class="form-control" placeholder="🔍 Buscar proyecto..." style="width:100%; padding: 12px; font-size: 1rem; border: 2px solid #007bff;">
			</div>
		</div>

		<div class="row">
			<div class="col-md-12">
				<?php $vHosts = loadClass('Httpd')->getVirtualHosts(); ?>
				<?php if ($vHosts): ?>

					<?php
					$vhost_to_group_map = [];				$vhost_alias_map = [];					$group_labels = [];

					// 1. Load from YAML file if exists
					$yaml_file = '/shared/httpd/vhost-groups.yml';
					if (file_exists($yaml_file)) {
						if (function_exists('yaml_parse_file')) {
							// Using YAML Extension
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
												if (!isset($vhost_alias_map)) {
													$vhost_alias_map = [];
												}
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
						// Simple custom parser for Group: \n  - site and alias support
						$lines = file($yaml_file);
						$current_group = '';
						$current_alias_base = null;
						foreach ($lines as $line) {
							$line = rtrim($line);
							$trimmed = trim($line);
							if (empty($trimmed) || strpos($trimmed, '#') === 0) continue;

							// Group header: GroupName:
							if (preg_match('/^([^ -][^:]*):\s*$/', $trimmed, $matches)) {
								$current_group = trim($matches[1]);
								$group_labels[] = $current_group;
								$current_alias_base = null;
								continue;
							}

							if (!$current_group) continue;

							// Alias mapping start: - base:
							if (preg_match('/^\s*-\s*([^:]+?):\s*$/', $line, $matches)) {
								$current_alias_base = trim($matches[1]);
								$vhost_to_group_map[$current_alias_base] = $current_group;
								continue;
							}

							// Alias entry under alias mapping:   - alias
							if ($current_alias_base && preg_match('/^\s{4,}[-*]\s*(.+)$/', $line, $matches)) {
								$alias = trim($matches[1]);
								if ($alias !== '') {
									$vhost_alias_map[$alias] = $current_alias_base;
								}
								continue;
							}

							// Normal single entry: - site
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

					// 2. Process grouping
					$grouped_vhosts = [];
					foreach (array_unique($group_labels) as $label) {
						$grouped_vhosts[$label] = [];
					}
					$grouped_vhosts['Otros'] = [];

					foreach ($vHosts as $vHost) {
						$site_name = $vHost['name'];
						// Skip if this is an alias (we'll show it under its canonical host)
						if (isset($vhost_alias_map[$site_name])) {
							continue;
						}

						if (isset($vhost_to_group_map[$site_name])) {
							$label = $vhost_to_group_map[$site_name];
							$grouped_vhosts[$label][] = $vHost;
						} elseif (isset($vhost_alias_map[$site_name]) && isset($vhost_to_group_map[$vhost_alias_map[$site_name]])) {
							$label = $vhost_to_group_map[$vhost_alias_map[$site_name]];
							$grouped_vhosts[$label][] = $vHost;
						} else {
							// Fallback to prefix matching if any prefixes are defined as "Prefix: Group" 
							// in the old style (this keeps backward compatibility if user mixes styles)
							$matched = false;
							foreach ($vhost_to_group_map as $key => $val) {
								if (substr($key, -1) === '_' && strpos($site_name, $key) === 0) {
									$grouped_vhosts[$val][] = $vHost;
									$matched = true;
									break;
								}
							}
							if (!$matched) {
								$grouped_vhosts['Otros'][] = $vHost;
							}
						}
					}
					?>

					<?php foreach ($grouped_vhosts as $group_label => $hosts): ?>
						<?php if (empty($hosts)) continue; ?>
						<h3 data-toggle="collapse" data-target="#group-<?php echo md5($group_label); ?>" style="cursor: pointer; margin-top: 20px; font-size: 1.4rem;" class="group-header" data-group="<?php echo md5($group_label); ?>">
							<i class="fa fa-folder-open-o" aria-hidden="true"></i> <?php echo htmlspecialchars($group_label); ?>
							<span class="badge badge-info" style="font-size: 0.8rem; vertical-align: middle; group-count"><?php echo count($hosts); ?></span>
						</h3>
						<div id="group-<?php echo md5($group_label); ?>" class="collapse show group-container" data-group="<?php echo md5($group_label); ?>">
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
							<tbody class="vhost-rows">
								<?php foreach ($hosts as $vHost): ?>
									<tr class="vhost-row" data-vhost="<?php echo strtolower($vHost['name']); ?>" data-aliases="<?php echo htmlspecialchars(implode(',', $aliases ?? [])); ?>">
										<td class="text-xs-center">
											<?php if ($logo = getProjectLogo($vHost['name'])): ?>
												<img src="<?php echo $logo; ?>" style="max-height:34px; max-width: 34px; vertical-align: middle; border-radius: 2px;" />
											<?php else: ?>
												<i class="fa fa-globe" aria-hidden="true" style="font-size: 34px; vertical-align: middle; color: #adb5bd;"></i>
											<?php endif; ?>
										</td>
								<?php
									$aliases = isset($vhost_alias_map) ? array_keys($vhost_alias_map, $vHost['name']) : [];
								?>
								<td id="td-href-<?php echo htmlspecialchars($vHost['name']); ?>" data-aliases="<?php echo htmlspecialchars(implode(',', $aliases)); ?>">
									<div class="vhost-link-container">
										<span id="href-<?php echo htmlspecialchars($vHost['name']); ?>"><?php echo htmlspecialchars($vHost['name']); ?></span>
										<input type="hidden" name="vhost[]" class="vhost" value="<?php echo htmlspecialchars($vHost['name']); ?>" />
									</div>
									<?php if (!empty($aliases)): ?>
										<?php foreach ($aliases as $alias): ?>
											<div class="alias-link-container" style="margin-top: 5px; padding-left: 10px; border-left: 2px solid #ddd;">
												<small style="font-weight: bold; color: #666;">Alias: <span id="href-<?php echo htmlspecialchars($alias); ?>"><?php echo htmlspecialchars($alias); ?></span></small>
												<input type="hidden" name="vhost[]" class="vhost" value="<?php echo htmlspecialchars($alias); ?>" />
												<span id="valid-<?php echo htmlspecialchars($alias); ?>" class="badge" style="font-size: 0.7rem;"></span>
											</div>
										<?php endforeach; ?>
									<?php endif; ?>
								</td>
										<td><?php echo loadClass('Helper')->getEnv('HOST_PATH_HTTPD_DATADIR'); ?>/<?php echo $vHost['name']; ?>/<?php echo loadClass('Helper')->getEnv('HTTPD_DOCROOT_DIR'); ?>
										</td>
										<td>
											<?php echo loadClass('Httpd')->getVhostBackend($vHost['name']); ?>
										</td>
										<td>
										<?php $id_vhost_httpd = str_replace('=', '', base64_encode('vhost_httpd_conf_' . $vHost['name'])); ?>
										<?php $id_vhost_vhostgen = str_replace('=', '', base64_encode('vhost_vhost_gen_' . $vHost['name'])); ?>

										<!-- [httpd.conf] Button trigger modal -->
										<a href="#"><i class="fa fa-cog" aria-hidden="true" data-toggle="modal"
												data-target="#<?php echo $id_vhost_httpd; ?>"></i></a>
										<!-- [httpd.conf] Modal -->
										<div class="modal" id="<?php echo $id_vhost_httpd; ?>" tabindex="-1" role="dialog"
											aria-labelledby="<?php echo $id_vhost_httpd; ?>Label" aria-hidden="true">
											<div class="modal-dialog modal-lg" role="document">
												<div class="modal-content">
													<div class="modal-header">
														<h5 class="modal-title" id="<?php echo $id_vhost_httpd; ?>Label">
															<?php echo '<strong>httpd.conf: </strong><code>' . $vHost['name'] . '.' . loadClass('Httpd')->getTldSuffix() . '</code>'; ?>
														</h5>
														<button type="button" class="close" data-dismiss="modal"
															aria-label="Close">
															<span aria-hidden="true">&times;</span>
														</button>
													</div>
													<div class="modal-body">
														<?php $url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]"; ?>
														<?php $src = file_get_contents($url . '/vhost.d/' . $vHost['name'] . '.conf'); ?>
														<?php //$src = htmlentities($src); ?>
														<?php $src = str_replace('<', '&lt;', $src); ?>
														<?php $src = str_replace('>', '&gt;', $src); ?>
														<?php $src = preg_replace('/&lt;(\/?.*)&gt;/m', '<strong>&lt;\1&gt;</strong>', $src); // Apache directives ?>
														<?php $src = preg_replace('/(.*{\s*)$/m', '<strong>\1</strong>', $src); // Nginx directives ?>
														<?php $src = preg_replace('/^(\s*}\s*)$/m', '<strong>\1</strong>', $src); // Nginx directives ?>
														<?php //$src = preg_replace('/"(.+)"/m',       '<span style="color: blue;">"\1"</span>', $src); ?>
														<?php $src = preg_replace('/^(\s*(?!<#)[^#"]*)"(.*)"/m', '\1<span style="color: blue;">"\2"</span>', $src);  // double quotes ?>
														<?php $src = preg_replace("/^(\s*(?!<#)[^#']*)'(.*)'/m", '\1<span style="color: blue;">"\2"</span>', $src);  // single quotes ?>
														<?php $src = preg_replace('/^(\s*#)(.*)$/m', '<span style="color: gray;">\1\2</span>', $src);  // comments ?>
														<?php echo '<pre><code>' . $src . '</code></pre>'; ?>
													</div>
													<div class="modal-footer">
														<button type="button" class="btn btn-secondary"
															data-dismiss="modal">Close</button>
													</div>
												</div>
											</div>
										</div>
										<?php if (($vhostGenPath = loadClass('Httpd')->getVhostgenTemplatePath($vHost['name'])) !== false): ?>
											<!-- [vhost-gen] Button trigger modal -->
											<a href="#"><i class="fa fa-filter" aria-hidden="true" data-toggle="modal"
													data-target="#<?php echo $id_vhost_vhostgen; ?>"></i></a>
											<!-- [vhost-gen] Modal -->
											<div class="modal" id="<?php echo $id_vhost_vhostgen; ?>" tabindex="-1" role="dialog"
												aria-labelledby="<?php echo $id_vhost_vhostgen; ?>Label" aria-hidden="true">
												<div class="modal-dialog modal-lg" role="document">
													<div class="modal-content">
														<div class="modal-header">
															<h5 class="modal-title" id="<?php echo $id_vhost_vhostgen; ?>Label">
																<?php echo '<code>' . $vhostGenPath . '</code>'; ?>
															</h5>
															<button type="button" class="close" data-dismiss="modal"
																aria-label="Close">
																<span aria-hidden="true">&times;</span>
															</button>
														</div>
														<div class="modal-body">
															<?php $src = file_get_contents($vhostGenPath); ?>
															<?php //$src = htmlentities($src); ?>
															<?php $src = str_replace('<', '&lt;', $src); ?>
															<?php $src = str_replace('>', '&gt;', $src); ?>
															<?php $src = preg_replace('/&lt;(\/?.*)&gt;/m', '<strong>&lt;\1&gt;</strong>', $src); // Apache directives ?>
															<?php $src = preg_replace('/(.*{\s*)$/m', '<strong>\1</strong>', $src); // Nginx directives ?>
															<?php $src = preg_replace('/^(\s*}\s*)$/m', '<strong>\1</strong>', $src); // Nginx directives ?>
															<?php //$src = preg_replace('/"(.+)"/m',        '<span style="color: blue;">"\1"</span>', $src); ?>
															<?php //$src = preg_replace("/'(.+)'/m",        '<span style="color: blue;">'."'".'\1'."'".'</span>', $src); ?>
															<?php $src = preg_replace('/^(\s*(?!<#)[^#"]*)"(.*)"/m', '\1<span style="color: blue;">"\2"</span>', $src); // double quotes ?>
															<?php $src = preg_replace("/^(\s*(?!<#)[^#']*)'(.*)'/m", '\1<span style="color: blue;">"\2"</span>', $src); // single quotes ?>
															<?php $src = preg_replace('/^(\s*#)(.*)$/m', '<span style="color: gray;">\1\2</span>', $src); // comments ?>
															<?php $src = preg_replace('/^(\s*[_a-z]+):/m', '<span style="color: green;"><strong>\1</strong></span>:', $src); // yaml keys ?>
															<?php $src = preg_replace('/(__[_A-Z]+__)/m', '<span style="color: red;">\1</span>', $src); // variables ?>
															<?php echo '<pre><code>' . $src . '</code></pre>'; ?>
														</div>
														<div class="modal-footer">
															<button type="button" class="btn btn-secondary"
																data-dismiss="modal">Close</button>
														</div>
													</div>
												</div>
											</div>
										<?php endif; ?>
									</td>
									<td class="text-xs-center text-xs-small" id="valid-<?php echo $vHost['name']; ?>"></td>
								</tr>
								<input type="hidden" name="vhost[]" class="vhost" value="<?php echo $vHost['name']; ?>" />
								<?php endforeach; ?>
							</tbody>
						</table>
						</div>
					<?php endforeach; ?>
				<?php else: ?>
					<h4>No projects here.</h4>
					<p>Simply create a directory in
						<strong><?php echo loadClass('Helper')->getEnv('HOST_PATH_HTTPD_DATADIR'); ?></strong> on your host
						computer (or in <strong>/shared/httpd</strong> inside the php container).
					</p>
					<p><strong>Example:</strong><br /><?php echo loadClass('Helper')->getEnv('HOST_PATH_HTTPD_DATADIR'); ?>/my_project
					</p>
					<p>It will then be available via
						<strong>http://my_project.<?php echo loadClass('Httpd')->getTldSuffix(); ?></strong>
					</p>
				<?php endif; ?>
			</div>
		</div>

		<?php
		$cmd = "netstat -wneeplt 2>/dev/null | sort | grep '\s1000\s' | awk '{print \"app=\"\$9\"|addr=\"\$4}' | sed 's/\(app=\)\([0-9]*\/\)/\\1/g' | sed 's/\(.*\)\(:[0-9][0-9]*\)/\\1|port=\\2/g' | sed 's/port=:/port=/g'";
		$output = loadClass('Helper')->exec($cmd);
		$daemons = array();
		foreach (preg_split("/((\r?\n)|(\r\n?))/", $output) as $line) {
			$section = preg_split("/\|/", $line);
			if (count($section) == 3) {
				$tool = preg_split("/=/", $section[0]);
				$addr = preg_split("/=/", $section[1]);
				$port = preg_split("/=/", $section[2]);
				$tool = $tool[1];
				$addr = $addr[1];
				$port = $port[1];
				$daemons[] = array(
					'tool' => $tool,
					'addr' => $addr,
					'port' => $port
				);
			}
		}
		?>
		<?php if (count($daemons)): ?>
			<br />
			<br />
			<div class="row">
				<div class="col-md-12">

					<h2>Local listening daemons</h2>
					<table class="table table-striped">
						<thead class="thead-inverse">
							<tr>
								<th>Application</th>
								<th>Listen Address</th>
								<th>Listen Port</th>
								<th>Host</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($daemons as $daemon): ?>
								<tr>
									<td><?php echo $daemon['tool']; ?></td>
									<td><?php echo $daemon['addr']; ?></td>
									<td><?php echo $daemon['port']; ?></td>
									<td>php (172.16.238.10)</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
		<?php endif; ?>



	</div><!-- /.container -->

	<?php echo loadClass('Html')->getFooter(); ?>
	<script>
		// self executing function here
		(function () {
			// your page initialization code here
			// the DOM will be available here

			// Filter functionality
			const filterInput = document.getElementById('vhostFilter');
			const groupHeaders = document.querySelectorAll('.group-header');
			const groupContainers = document.querySelectorAll('.group-container');
			const vhostRows = document.querySelectorAll('.vhost-row');

			function filterVhosts() {
				const filterValue = filterInput.value.toLowerCase().trim();
				let visibleGroups = new Set();

				// Filter rows
				vhostRows.forEach(row => {
					const vhostName = row.getAttribute('data-vhost');
					const aliases = row.getAttribute('data-aliases').split(',').map(a => a.toLowerCase());
					
					// Check if vhost or any alias matches the filter
					const matches = !filterValue || 
						vhostName.includes(filterValue) || 
						aliases.some(alias => alias.includes(filterValue));

					if (matches) {
						row.style.display = '';
						// Get the group this row belongs to
						const tbody = row.closest('.vhost-rows');
						const container = tbody.closest('.group-container');
						if (container) {
							const group = container.getAttribute('data-group');
							if (group) visibleGroups.add(group);
						}
					} else {
						row.style.display = 'none';
					}
				});

				// Show/hide groups and headers
				groupContainers.forEach(container => {
					const group = container.getAttribute('data-group');
					const shouldShow = visibleGroups.has(group);
					const header = document.querySelector(`.group-header[data-group="${group}"]`);
					
					if (shouldShow) {
						container.style.display = '';
						if (header) header.style.display = '';
					} else {
						container.style.display = 'none';
						if (header) header.style.display = 'none';
					}
				});

				// If no filter, show all
				if (!filterValue) {
					groupHeaders.forEach(h => h.style.display = '');
					groupContainers.forEach(c => c.style.display = '');
					vhostRows.forEach(r => r.style.display = '');
				}
			}

			// Add event listener for filter input
			filterInput.addEventListener('input', filterVhosts);
			filterInput.addEventListener('keyup', filterVhosts);

			function updateStatus(vhost) {
				var xhttp = new XMLHttpRequest();

				xhttp.onreadystatechange = function () {
					var error = '';
					var el_valid;
					var el_href;

					if (this.readyState == 4 && this.status == 200 || this.status == 426) {
						el_valid = document.getElementById('valid-' + vhost);
						el_href = document.getElementById('href-' + vhost);
						error = this.responseText;

						if (error.length && error.match(/^error/)) {
							if (el_valid) { el_valid.className += ' bg-danger'; el_valid.innerHTML = 'ERR'; }
							el_href.innerHTML = error;
						} else if (error.length && error.match(/^warning/)) {
							if (el_valid) { el_valid.className += ' bg-warning'; el_valid.innerHTML = 'WARN'; }
							el_href.innerHTML = error.replace('warning', '');
							checkDns(vhost);
						} else {
							checkDns(vhost);
						}
					}
				};
				xhttp.open('GET', '_ajax_callback.php?vhost=' + vhost, true);
				xhttp.send();
			}

			/**
			 * Check if DNS record is set in /etc/hosts (or via attached DNS server)
			 * for TLD_SUFFIX
			 */
			function checkDns(vhost) {
				var xhttp = new XMLHttpRequest();
				var proto;
				var port;
				var name = vhost + '.<?php echo loadClass('Httpd')->getTldSuffix(); ?>'

				var url = window.location.href.split("/");
				var tmp = url[2].split(":");
				proto = url[0];
				port = tmp.length == 2 ? ':' + tmp[1] : '';

				// Timeout after XXX seconds and mark it invalid DNS
				xhttp.timeout = <?php echo loadClass('Helper')->getEnv('DNS_CHECK_TIMEOUT'); ?>000;

				xhttp.onreadystatechange = function (e) {
					var el_valid = document.getElementById('valid-' + vhost);
					var el_href = document.getElementById('href-' + vhost);
					var error = this.responseText;

					if (this.readyState == 4 && (this.status == 200 || this.status == 426)) {
						clearTimeout(xmlHttpTimeout);
						if (el_valid) {
							el_valid.className += ' bg-success';
							if (el_valid.innerHTML != 'WARN') {
								el_valid.innerHTML = 'OK';
							}
						}
						el_href.innerHTML = '<a target="_blank" href="' + proto + '//' + name + port + '">' + name + port + '</a>';
					} else {
						//console.log(vhost);
					}
				}
				xhttp.open('POST', proto + '//' + name + port + '/devilbox-api/status.json', true);
				xhttp.send();

				// Timeout to abort in 1 second
				var xmlHttpTimeout = setTimeout(ajaxTimeout, <?php echo loadClass('Helper')->getEnv('DNS_CHECK_TIMEOUT'); ?> * 1000);
				function ajaxTimeout(e) {
					var el_valid = document.getElementById('valid-' + vhost);
					var el_href = document.getElementById('href-' + vhost);
					var error = this.responseText;

					if (el_valid) {
						el_valid.className += ' bg-danger';
						el_valid.innerHTML = 'ERR';
					}
					el_href.innerHTML = 'No Host DNS record found. Add the following to <code>/etc/hosts</code>:<br/><code>127.0.0.1 ' + vhost + '.<?php echo loadClass('Httpd')->getTldSuffix(); ?></code>';
				}

			}

			var vhosts = document.getElementsByName('vhost[]');

			for (i = 0; i < vhosts.length; i++) {
				updateStatus(vhosts[i].value);
			}
		})();


	</script>
</body>

</html>