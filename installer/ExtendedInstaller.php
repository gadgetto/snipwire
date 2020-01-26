<?php namespace ProcessWire;

/**
 * Extended resources installer for SnipWire.
 * (This file is part of the SnipWire package)
 * 
 * Licensed under MPL 2.0 (see LICENSE file provided with this package)
 * Copyright 2019 by Martin Gartner
 *
 * ProcessWire 3.x, Copyright 2019 by Ryan Cramer
 * https://processwire.com
 *
 */

class ExtendedInstaller extends Wire {

    const installerModeTemplates = 1;
    const installerModeFields = 2;
    const installerModePages = 4;
    const installerModePermissions = 8;
    const installerModeFiles = 16;
    const installerModeAll = 31;

    /**var string $snipWireRootUrl The root URL to ProcessSnipWire page */
    protected $snipWireRootUrl = '';

    /** @var string $resourcesFile Name of file which holds installer resources */
    protected $resourcesFile = '';
    
    /** @var array $resources Installation resources */
    protected $resources = array();
    
    /**
     * Constructor for ExtendedInstaller class.
     * 
     * @return void
     * 
     */
    public function __construct() {
        $this->snipWireRootUrl = rtrim($this->wire('pages')->findOne('template=admin, name=snipwire')->url, '/') . '/';
        parent::__construct();
    }

    /**
     * Retrieve extended installation resources from file.
     * (file needs to be in same folder as this class file)
     * 
     * @param string $fileName The name of the resources file
     * @return array
     * @throws WireException
     * 
     */
    public function setResourcesFile($fileName) {
        $path = dirname(__FILE__) . DIRECTORY_SEPARATOR . $fileName;
        if (file_exists($path)) {
            include $path;
            if (!is_array($resources) || !count($resources)) {
                $out = sprintf($this->_('Installation aborted. Invalid resources array in file [%s].'), $resources);
                throw new WireException($out);
            }
        } else  {
            $out = sprintf($this->_('Installation aborted. File [%s] not found.'), $resources);
            throw new WireException($out);
        }
        $this->resources = $resources;
        return $this->resources;
    }

    /**
     * Set installation resources from array.
     * (multidimensional array)
     * 
     * @param array $resources Installation resources array
     * @return array
     * @throws WireException
     * 
     */
    public function setResources($resources) {
        if (!is_array($resources) || !count($resources)) {
            $out = $this->_('Installation aborted. Invalid resources array.');
            throw new WireException($out);
        }
        $this->resources = $resources;
        return $this->resources;
    }

    /**
     * Installer for extended resources from [ClassName].resources.php.
     *
     * @param integer $mode
     * @return boolean true | false (if installations has errors or warnings)
     *
     */
    public function installResources($mode = self::installerModeAll) {
        $fields      = $this->wire('fields');
        $fieldgroups = $this->wire('fieldgroups');
        $templates   = $this->wire('templates');
        $pages       = $this->wire('pages');
        $permissions = $this->wire('permissions');
        $modules     = $this->wire('modules');
        $config      = $this->wire('config');
        
        if (!$this->resources) {
            $out = $this->_('Installation aborted. No resources array provided. Please use "setResourcesFile" or "setResources" method to provide a resources array.');
            throw new WireException($out);
        }
        
        $sourceDir = dirname(__FILE__) . '/';
        
        /* ====== Install templates ====== */
        
        if (!empty($this->resources['templates']) && is_array($this->resources['templates']) && $mode & self::installerModeTemplates) {
            foreach ($this->resources['templates'] as $item) {
                if (!$templates->get($item['name'])) {
                    $fg = new Fieldgroup();
                    $fg->name = $item['name'];
                    // Add title field (mandatory!)
                    $fg->add($fields->get('title'));
                    $fg->save();             
                   
                    $t = new Template();
                    $t->name = $item['name'];
                    $t->fieldgroup = $fg;
                    $t->label = $item['label'];
                    if (isset($item['icon'])) $t->setIcon($item['icon']);
                    if (isset($item['noChildren'])) $t->noChildren = $item['noChildren'];
                    if (isset($item['noParents'])) $t->noParents = $item['noParents'];
                    if (isset($item['tags'])) $t->tags = $item['tags'];
                    $t->save();
                    $this->message($this->_('Installed Template: ') . $item['name']);
                } else {
                    $this->warning(sprintf($this->_("Template [%s] already exists. Skipped installation."), $item['name']));
                }
            }
            
            // Solve template dependencies (after installation of all templates!)
            foreach ($this->resources['templates'] as $item) {
                if ($t = $templates->get($item['name'])) {
                    $pt = array();
                    if (!empty($item['_allowedParentTemplates'])) {
                        foreach (explode(',', $item['_allowedParentTemplates']) as $ptn) {
                            $pt[] += $templates->get($ptn)->id; // needs to be added as array of template IDs
                        }
                        $t->parentTemplates = $pt;
                    }
                    $ct = array();
                    if (!empty($item['_allowedChildTemplates'])) {
                        foreach (explode(',', $item['_allowedChildTemplates']) as $ctn) {
                            $ct[] += $templates->get($ctn)->id; // needs to be added as array of template IDs
                        }
                        $t->childTemplates = $ct;
                    }
                    $t->save();
                }
            }
        }
        
        /* ====== Install files ====== */
        
        if (!empty($this->resources['files']) && is_array($this->resources['files']) && $mode & self::installerModeFiles) {
            foreach ($this->resources['files'] as $file) {
                $source = $sourceDir . $file['type'] . '/' . $file['name'];
                $destination = $config->paths->templates . $file['name'];
                if (!file_exists($destination)) {
                    if ($this->wire('files')->copy($source, $destination)) {
                        $this->message(sprintf($this->_('Installed file [%1$s] to [%2$s].'), $source, $destination));
                    } else {
                        $this->error(sprintf($this->_('Could not copy file from [%1$s] to [%2$s]. Please copy manually.'), $source, $destination));
                    }
                } else {
                    $this->warning(sprintf($this->_('File [%2$s] already exists. If necessary please copy manually from [%1$s]. Skipped installation.'), $source, $destination));
                }
            }
        }

        /* ====== Install fields ====== */
        
        if (!empty($this->resources['fields']) && is_array($this->resources['fields']) && $mode & self::installerModeFields) {
            foreach ($this->resources['fields'] as $item) {
                if (!$fields->get($item['name'])) {
                    $f = new Field();
                    if (!$f->type = $modules->get($item['type'])) {
                        $this->error(sprintf($this->_("Field [%s] could not be installed. Fieldtype [%s] not available. Skipped installation."), $item['name'], $item['type']));
                        continue;
                    }
                    $f->name = $item['name'];
                    $f->label = $item['label'];
                    if (isset($item['label2'])) $f->label2 = $item['label2'];
                    if (isset($item['description'])) $f->description = $item['description'];
                    if (isset($item['notes'])) $f->notes = $item['notes'];
                    if (isset($item['collapsed'])) $f->collapsed = $item['collapsed'];
                    if (isset($item['maxlength'])) $f->maxlength = $item['maxlength'];
                    if (isset($item['rows'])) $f->rows = $item['rows'];
                    if (isset($item['columnWidth'])) $f->columnWidth = $item['columnWidth'];
                    if (isset($item['defaultValue'])) $f->defaultValue = $item['defaultValue'];
                    if (isset($item['min'])) $f->min = $item['min'];
                    if (isset($item['inputType'])) $f->inputType = $item['inputType'];
                    if (isset($item['inputfield'])) $f->inputfield = $item['inputfield'];
                    if (isset($item['labelFieldName'])) $f->labelFieldName = $item['labelFieldName'];
                    if (isset($item['usePageEdit'])) $f->usePageEdit = $item['usePageEdit'];
                    if (isset($item['addable'])) $f->addable = $item['addable'];
                    if (isset($item['derefAsPage'])) $f->derefAsPage = $item['derefAsPage'];
                    // Used for AsmSelect
                    if (isset($item['parent_id'])) {
                        if (is_int($item['parent_id'])) {
                            $f->parent_id = $item['parent_id'];
                        } else {
                            $f->parent_id = $pages->get($item['parent_id'])->id;
                        }
                    }
                    // Used for AsmSelect
                    if (isset($item['template_id'])) {
                        if (is_int($item['template_id'])) {
                            $f->template_id = $item['template_id'];
                        } else {
                            $f->template_id = $templates->get($item['template_id'])->id;
                        }
                    }
                    if (isset($item['showCount'])) $f->showCount = $item['showCount'];
                    if (isset($item['stripTags'])) $f->stripTags = $item['stripTags'];
                    if (isset($item['textformatters']) && is_array($item['textformatters'])) $f->textformatters = $item['textformatters'];
                    if (isset($item['required'])) $f->required = $item['required'];
                    if (isset($item['extensions'])) $f->extensions = $item['extensions']; // for image and file fields
                    if (isset($item['pattern'])) $f->pattern = $item['pattern'];
                    if (isset($item['tags'])) $f->tags = $item['tags'];
                    if (isset($item['taxesType'])) $f->taxesType = $item['taxesType'];
                    $f->save();
                    $this->message($this->_('Installed Field: ') . $item['name']);
                } else {
                    $this->warning(sprintf($this->_("Field [%s] already exists. Skipped installation."), $item['name']));
                }

            }

            // Add fields to their desired templates */
            foreach ($this->resources['fields'] as $item) {
                if (!empty($item['_addToTemplates'])) {
                    foreach (explode(',', $item['_addToTemplates']) as $tn) {
                        if ($t = $templates->get($tn)) {
                            $fg = $t->fieldgroup;
                            if ($fg->hasField($item['name'])) continue; // No need to add - already added!
                            $f = $fields->get($item['name']);
                            $fg->add($f);
                            $fg->save();
                        } else {
                            $out = sprintf($this->_("Could not add field [%s] to template [%s]. The template does not exist!"), $item['name'], $tn);
                            $this->warning($out);
                        }
                    }
                }
            }
            
            // Configure fields in their templates context (overriding field options per template) */
            foreach ($this->resources['fields'] as $item) {
                if (!empty($item['_templateFieldOptions'])) {
                    foreach ($item['_templateFieldOptions'] as $tn => $options) {
                        if ($t = $templates->get($tn)) {
                            $fg = $t->fieldgroup;
                            if ($fg->hasField($item['name'])) {
                                $f = $fg->getField($item['name'], true);
                                if (isset($options['label'])) $f->label = $options['label'];
                                if (isset($options['notes'])) $f->notes = $options['notes'];
                                if (isset($options['columnWidth'])) $f->columnWidth = $options['columnWidth'];
                                $fields->saveFieldgroupContext($f, $fg);
                            } else {
                                $out = sprintf($this->_("Could not configure options of field [%s] in template context [%s]. The field is not assigned to template!"), $item['name'], $tn);
                                $this->warning($out);
                            }
                        } else {
                            $out = sprintf($this->_("Could not configure options of field [%s] in template context [%s]. The template does not exist!"), $item['name'], $tn);
                            $this->warning($out);
                        }
                    }
                }
            }            
        }

        /* ====== Install pages ====== */

        if (!empty($this->resources['pages']) && is_array($this->resources['pages']) && $mode & self::installerModePages) {
            foreach ($this->resources['pages'] as $item) {

                // Page "parent" key may have "string tags"
                $parent = wirePopulateStringTags(
                    $item['parent'],
                    array('snipWireRootUrl' => $this->snipWireRootUrl)
                );

                if (!$t = $templates->get($item['template'])) {
                    $out = sprintf($this->_("Skipped installation of page [%s]. The template [%s] to be assigned does not exist!"), $item['name'], $item['template']);
                    $this->error($out);
                    continue;
                }
                if (!$this->wire('pages')->get($parent)) {
                    $out = sprintf($this->_("Skipped installation of page [%s]. The parent [%s] to be set does not exist!"), $item['name'], $parent);
                    $this->error($out);
                    continue;
                }
                
                if (!$pages->findOne('name=' . $item['name'])->id) {
                    $page = new Page();
                    $page->name = $item['name'];
                    $page->template = $item['template'];
                    $page->parent = $parent;
                    $page->process = $this;
                    $page->title = $item['title'];
                    if (isset($item['status'])) $page->status = $item['status'];
                    $page->save();
                    $this->message($this->_('Installed Page: ') . $page->path);
                    
                    // Populate page-field values
                    if (!empty($item['fields']) && is_array($item['fields'])) {
                        foreach ($item['fields'] as $fieldname => $value) {
                            if ($page->hasField($fieldname)) {
                                $type = $page->getField($fieldname)->type;
                                if ($type == 'FieldtypeImage') {
                                    $source = $sourceDir . $value;
                                    $page->$fieldname->add($source);
                                } else {
                                    $page->$fieldname = $value;
                                }
                            }
                        }
                    }
                    $page->save();
                } else {
                    $this->warning(sprintf($this->_("Page [%s] already exists. Skipped installation."), $item['name']));
                }
            }
        }

        /* ====== Install permissions ====== */

        if (!empty($this->resources['permissions']) && is_array($this->resources['permissions'])) {
            foreach ($this->resources['permissions'] as $item) {
                if (!$permission = $permissions->get('name=' . $item['name'])) {
                    $p = new Permission();
                    $p->name = $item['name'];
                    $p->title = $item['title'];
                    $p->save();
                    $this->message($this->_('Installed Permission: ') . $item['name']);
                } else {
                    $this->warning(sprintf($this->_("Permission [%s] already exists. Skipped installation."), $item['name']));
                }
            }
        }
        
        return ($this->wire('notices')->hasErrors()) ? false : true;    
    }
}
