<?php
/**
 * Google Scholar Citation Widget Plugin for OJS
 * File: plugins/generic/scholarWidget/ScholarWidgetPlugin.inc.php
 */

import('lib.pkp.classes.plugins.GenericPlugin');

class ScholarWidgetPlugin extends GenericPlugin {
    
    public function register($category, $path, $mainContextId = null) {
        $success = parent::register($category, $path, $mainContextId);
        if ($success && $this->getEnabled($mainContextId)) {
            HookRegistry::register('Templates::Common::Sidebar', array($this, 'addSidebarWidget'));
        }
        return $success;
    }
    
    public function getDisplayName() {
        return 'Google Scholar Citation Widget';
    }
    
    public function getDescription() {
        return 'Menampilkan statistik sitasi dari Google Scholar dengan opsi pengaturan posisi';
    }
    
    /**
     * Get plugin settings
     */
    public function getActions($request, $actionArgs) {
        $actions = parent::getActions($request, $actionArgs);
        if (!$this->getEnabled()) {
            return $actions;
        }
        
        $router = $request->getRouter();
        import('lib.pkp.classes.linkAction.request.AjaxModal');
        $actions[] = new LinkAction(
            'settings',
            new AjaxModal(
                $router->url($request, null, null, 'manage', null, array('verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic')),
                $this->getDisplayName()
            ),
            __('manager.plugins.settings'),
            null
        );
        
        return $actions;
    }
    
    /**
     * Manage plugin settings
     */
    public function manage($args, $request) {
        switch ($request->getUserVar('verb')) {
            case 'settings':
                $context = $request->getContext();
                $templateMgr = TemplateManager::getManager($request);
                $templateMgr->register_function('plugin_url', array($this, 'smartyPluginUrl'));
                
                $this->import('ScholarWidgetSettingsForm');
                $form = new ScholarWidgetSettingsForm($this, $context->getId());
                
                if ($request->getUserVar('save')) {
                    $form->readInputData();
                    if ($form->validate()) {
                        $form->execute();
                        return new JSONMessage(true);
                    }
                } else {
                    $form->initData();
                }
                return new JSONMessage(true, $form->fetch($request));
        }
        return parent::manage($args, $request);
    }
    
    /**
     * Fetch citation data from Google Scholar
     */
    private function fetchScholarData($userId) {
        $cacheFile = 'cache/scholar_' . $userId . '.json';
        $cacheTime = 86400; // 24 jam
        
        // Check cache
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTime) {
            return json_decode(file_get_contents($cacheFile), true);
        }
        
        // Fetch new data
        $url = "https://scholar.google.com/citations?user=" . $userId . "&hl=en";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $html = curl_exec($ch);
        curl_close($ch);
        
        if (!$html) {
            return null;
        }
        
        // Parse HTML untuk mendapatkan data sitasi
        $data = $this->parseScholarHTML($html);
        
        // Save to cache
        if (!is_dir('cache')) {
            mkdir('cache', 0755, true);
        }
        file_put_contents($cacheFile, json_encode($data));
        
        return $data;
    }
    
    /**
     * Parse Google Scholar HTML
     */
    private function parseScholarHTML($html) {
        $data = array(
            'total_citations' => 0,
            'h_index' => 0,
            'i10_index' => 0,
            'citations_per_year' => array()
        );
        
        // Extract total citations
        if (preg_match('/<td class="gsc_rsb_std">(\d+)<\/td>/i', $html, $matches)) {
            $data['total_citations'] = (int)$matches[1];
        }
        
        // Extract h-index dan i10-index
        if (preg_match_all('/<td class="gsc_rsb_std">(\d+)<\/td>/i', $html, $matches)) {
            if (isset($matches[1][1])) $data['h_index'] = (int)$matches[1][1];
            if (isset($matches[1][2])) $data['i10_index'] = (int)$matches[1][2];
        }
        
        // Extract citations per year dengan parsing yang lebih baik
        if (preg_match('/<div class="gsc_md_hist_b">(.*?)<\/div>/s', $html, $histMatch)) {
            $histContent = $histMatch[1];
            
            // Extract years
            preg_match_all('/<span class="gsc_g_t">(\d{4})<\/span>/', $histContent, $yearMatches);
            
            // Extract citation counts
            preg_match_all('/<a[^>]*class="gsc_g_a"[^>]*style="[^"]*height:(\d+)px"[^>]*><span class="gsc_g_al">(\d+)<\/span><\/a>/', $histContent, $countMatches);
            
            if (!empty($yearMatches[1]) && !empty($countMatches[2])) {
                for ($i = 0; $i < count($yearMatches[1]); $i++) {
                    $year = $yearMatches[1][$i];
                    $count = isset($countMatches[2][$i]) ? (int)$countMatches[2][$i] : 0;
                    $data['citations_per_year'][$year] = $count;
                }
            }
        }
        
        $data['last_updated'] = date('Y-m-d H:i:s');
        
        return $data;
    }
    
    /**
     * Add sidebar widget
     */
    public function addSidebarWidget($hookName, $params) {
        $smarty =& $params[1];
        $output =& $params[2];
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        
        if (!$context) {
            return false;
        }
        
        // Get settings
        $scholarUserId = $this->getSetting($context->getId(), 'scholarUserId');
        $widgetPosition = $this->getSetting($context->getId(), 'widgetPosition');
        $showHIndex = $this->getSetting($context->getId(), 'showHIndex');
        $showI10Index = $this->getSetting($context->getId(), 'showI10Index');
        $showCitationBar = $this->getSetting($context->getId(), 'showCitationBar');
        $widgetTitle = $this->getSetting($context->getId(), 'widgetTitle');
        
        // Default values
        if (empty($scholarUserId)) {
            $scholarUserId = 'T2zmL94AAAAJ'; // Default ke jurnal Anda
        }
        if (empty($widgetPosition)) {
            $widgetPosition = 'bottom'; // Default di bawah
        }
        if (!isset($showHIndex)) {
            $showHIndex = true;
        }
        if (!isset($showI10Index)) {
            $showI10Index = true;
        }
        if (!isset($showCitationBar)) {
            $showCitationBar = true;
        }
        if (empty($widgetTitle)) {
            $widgetTitle = 'Statistik Sitasi';
        }
        
        $data = $this->fetchScholarData($scholarUserId);
        
        if ($data) {
            // Build stats items
            $statsHtml = '<div class="stat-item">
                            <div class="stat-value">' . number_format($data['total_citations']) . '</div>
                            <div class="stat-label">Total Sitasi</div>
                        </div>';
            
            if ($showHIndex) {
                $statsHtml .= '<div class="stat-item">
                                <div class="stat-value">' . $data['h_index'] . '</div>
                                <div class="stat-label">h-index</div>
                            </div>';
            }
            
            if ($showI10Index) {
                $statsHtml .= '<div class="stat-item">
                                <div class="stat-value">' . $data['i10_index'] . '</div>
                                <div class="stat-label">i10-index</div>
                            </div>';
            }
            
            // Calculate grid columns
            $columns = 1;
            if ($showHIndex) $columns++;
            if ($showI10Index) $columns++;
            
            // Build citation bar chart
            $citationBarHtml = '';
            if ($showCitationBar && !empty($data['citations_per_year'])) {
                // Sort by year
                ksort($data['citations_per_year']);
                
                // Find max value for scaling
                $maxCitations = max($data['citations_per_year']);
                
                $citationBarHtml = '<div class="citation-chart">
                    <h3 class="chart-title">Sitasi per Tahun</h3>
                    <div class="chart-container">';
                
                foreach ($data['citations_per_year'] as $year => $count) {
                    $percentage = $maxCitations > 0 ? ($count / $maxCitations) * 100 : 0;
                    $citationBarHtml .= '
                        <div class="chart-bar-wrapper">
                            <div class="chart-bar" style="height: ' . $percentage . '%;" title="' . $year . ': ' . $count . ' sitasi">
                                <span class="bar-value">' . $count . '</span>
                            </div>
                            <div class="chart-year">' . $year . '</div>
                        </div>';
                }
                
                $citationBarHtml .= '</div></div>';
            }
            
            $widgetHtml = '
            <div class="pkp_block block_custom scholar-widget" data-position="' . $widgetPosition . '">
                <h2 class="title">' . htmlspecialchars($widgetTitle) . '</h2>
                <div class="content">
                    <div class="scholar-stats" style="grid-template-columns: repeat(' . $columns . ', 1fr);">
                        ' . $statsHtml . '
                    </div>
                    ' . $citationBarHtml . '
                    <div class="scholar-link">
                        <a href="https://scholar.google.com/citations?user=' . $scholarUserId . '" target="_blank" rel="noopener">
                            Lihat di Google Scholar →
                        </a>
                    </div>
                    <div class="last-updated">
                        Diperbarui: ' . date('d/m/Y', strtotime($data['last_updated'])) . '
                    </div>
                </div>
            </div>
            <style>
                .scholar-widget .scholar-stats {
                    display: grid;
                    gap: 15px;
                    margin: 15px 0;
                }
                .scholar-widget .stat-item {
                    text-align: center;
                    padding: 10px;
                    background: #f5f5f5;
                    border-radius: 5px;
                }
                .scholar-widget .stat-value {
                    font-size: 24px;
                    font-weight: bold;
                    color: #1a73e8;
                }
                .scholar-widget .stat-label {
                    font-size: 12px;
                    color: #666;
                    margin-top: 5px;
                }
                
                /* Citation Bar Chart Styles */
                .scholar-widget .citation-chart {
                    margin: 20px 0;
                    padding: 15px;
                    background: #fafafa;
                    border-radius: 5px;
                }
                .scholar-widget .chart-title {
                    font-size: 14px;
                    font-weight: bold;
                    color: #333;
                    margin: 0 0 15px 0;
                    text-align: center;
                }
                .scholar-widget .chart-container {
                    display: flex;
                    align-items: flex-end;
                    justify-content: space-around;
                    height: 120px;
                    padding: 10px 5px;
                    gap: 4px;
                }
                .scholar-widget .chart-bar-wrapper {
                    flex: 1;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    height: 100%;
                    position: relative;
                }
                .scholar-widget .chart-bar {
                    width: 100%;
                    max-width: 30px;
                    background: linear-gradient(to top, #1a73e8, #4285f4);
                    border-radius: 3px 3px 0 0;
                    position: relative;
                    transition: all 0.3s ease;
                    cursor: pointer;
                    min-height: 5px;
                }
                .scholar-widget .chart-bar:hover {
                    background: linear-gradient(to top, #1557b0, #1a73e8);
                    transform: translateY(-2px);
                }
                .scholar-widget .bar-value {
                    position: absolute;
                    top: -20px;
                    left: 50%;
                    transform: translateX(-50%);
                    font-size: 10px;
                    font-weight: bold;
                    color: #333;
                    white-space: nowrap;
                    opacity: 0;
                    transition: opacity 0.3s ease;
                }
                .scholar-widget .chart-bar:hover .bar-value {
                    opacity: 1;
                }
                .scholar-widget .chart-year {
                    font-size: 10px;
                    color: #666;
                    margin-top: 5px;
                    text-align: center;
                }
                
                .scholar-widget .scholar-link {
                    text-align: center;
                    margin: 15px 0;
                }
                .scholar-widget .scholar-link a {
                    color: #1a73e8;
                    text-decoration: none;
                }
                .scholar-widget .last-updated {
                    font-size: 11px;
                    color: #999;
                    text-align: center;
                }
            </style>
            <script>
                // Reorder widget based on position setting
                document.addEventListener("DOMContentLoaded", function() {
                    var widget = document.querySelector(".scholar-widget");
                    if (widget) {
                        var position = widget.getAttribute("data-position");
                        var sidebar = widget.parentElement;
                        
                        if (position === "top") {
                            sidebar.insertBefore(widget, sidebar.firstChild);
                        } else if (position === "middle") {
                            var blocks = sidebar.querySelectorAll(".pkp_block");
                            var middleIndex = Math.floor(blocks.length / 2);
                            if (blocks[middleIndex]) {
                                sidebar.insertBefore(widget, blocks[middleIndex]);
                            }
                        }
                        // "bottom" is default, no action needed
                    }
                });
            </script>
            ';
            
            $output .= $widgetHtml;
        }
        
        return false;
    }
}
?>