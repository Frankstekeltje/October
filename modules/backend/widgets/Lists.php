<?php namespace Backend\Widgets;

use Db;
use Str;
use Html;
use Lang;
use Backend;
use DbDongle;
use Carbon\Carbon;
use October\Rain\Html\Helper as HtmlHelper;
use October\Rain\Router\Helper as RouterHelper;
use System\Helpers\DateTime as DateTimeHelper;
use System\Classes\PluginManager;
use Backend\Classes\ListColumn;
use Backend\Classes\WidgetBase;
use October\Rain\Database\Model;
use October\Rain\Element\Lists\ColumnDefinition;
use October\Contracts\Element\ListElement;
use ApplicationException;
use BackendAuth;
use Exception;

/**
 * Lists Widget
 * Used for building back end lists, renders a list of model objects
 *
 * @package october\backend
 * @author Alexey Bobkov, Samuel Georges
 */
class Lists extends WidgetBase implements ListElement
{
    use Backend\Traits\PreferenceMaker;

    //
    // Configurable properties
    //

    /**
     * @var array List column configuration.
     */
    public $columns;

    /**
     * @var Model List model object.
     */
    public $model;

    /**
     * @var string Link for each record row. Replace :id with the record id.
     */
    public $recordUrl;

    /**
     * @var string Click event for each record row. Replace :id with the record id.
     */
    public $recordOnClick;

    /**
     * @var string Message to display when there are no records in the list.
     */
    public $noRecordsMessage = 'backend::lang.list.no_records';

    /**
     * @var int Maximum rows to display for each page.
     */
    public $recordsPerPage;

    /**
     * @var array perPageOptions is the number of items per page.
     */
    public $perPageOptions;

    /**
     * @var bool Shows the sorting options for each column.
     */
    public $showSorting = true;

    /**
     * @var mixed A default sort column to look for.
     */
    public $defaultSort;

    /**
     * @var bool Display a checkbox next to each record row.
     */
    public $showCheckboxes = false;

    /**
     * @var bool Display the list set up used for column visibility and ordering.
     */
    public $showSetup = false;

    /**
     * @var bool|string Display pagination when limiting records per page.
     */
    public $showPagination = 'auto';

    /**
     * @var bool Display page numbers with pagination, disable to improve performance.
     */
    public $showPageNumbers = true;

    /**
     * @var string Specify a custom view path to override partials used by the list.
     */
    public $customViewPath;

    //
    // Object properties
    //

    /**
     * @inheritDoc
     */
    protected $defaultAlias = 'list';

    /**
     * @var array Collection of all list columns used in this list.
     * @see Backend\Classes\ListColumn
     */
    protected $allColumns;

    /**
     * @var array Override default columns with supplied key names.
     */
    protected $columnOverride;

    /**
     * @var array Columns to display and their order.
     */
    protected $visibleColumns;

    /**
     * @var object Model data collection.
     */
    protected $records;

    /**
     * @var int Current page number.
     */
    protected $currentPageNumber;

    /**
     * @var string Filter the records by a search term.
     */
    protected $searchTerm;

    /**
     * @var string If searching the records, specifies a policy to use.
     * - all: result must contain all words
     * - any: result can contain any word
     * - exact: result must contain the exact phrase
     */
    protected $searchMode;

    /**
     * @var string Use a custom scope method for performing searches.
     */
    protected $searchScope;

    /**
     * @var array Collection of functions to apply to each list query.
     */
    protected $filterCallbacks = [];

    /**
     * @var array All sortable columns.
     */
    protected $sortableColumns;

    /**
     * @var string Sets the list sorting column.
     */
    protected $sortColumn;

    /**
     * @var string Sets the list sorting direction (asc, desc)
     */
    protected $sortDirection;

    /**
     * @var array List of CSS classes to apply to the list container element
     */
    public $cssClasses = [];

    /**
     * Initialize the widget, called by the constructor and free from its parameters.
     */
    public function init()
    {
        $this->fillFromConfig([
            'columns',
            'model',
            'recordUrl',
            'recordOnClick',
            'noRecordsMessage',
            'showPageNumbers',
            'recordsPerPage',
            'showSorting',
            'perPageOptions',
            'defaultSort',
            'showCheckboxes',
            'showSetup',
            'showPagination',
            'customViewPath',
        ]);

        /*
         * Configure the list widget
         */
        if ($this->showSetup) {
            $this->recordsPerPage = $this->getUserPreference('per_page', $this->recordsPerPage);
        }

        if ($this->showPagination == 'auto') {
            $this->showPagination = $this->recordsPerPage && $this->recordsPerPage > 0;
        }

        if ($this->customViewPath) {
            $this->addViewPath($this->customViewPath);
        }

        $this->validateModel();
    }

    /**
     * @inheritDoc
     */
    protected function loadAssets()
    {
        $this->addJs('js/october.list.js', 'core');
    }

    /**
     * Renders the widget.
     */
    public function render()
    {
        $this->prepareVars();
        return $this->makePartial('list-container');
    }

    /**
     * prepareVars for display
     */
    public function prepareVars()
    {
        $this->vars['cssClasses'] = implode(' ', $this->cssClasses);
        $this->vars['columns'] = $this->getVisibleColumns();
        $this->vars['columnTotal'] = $this->getTotalColumns();
        $this->vars['records'] = $this->getRecords();
        $this->vars['noRecordsMessage'] = trans($this->noRecordsMessage);
        $this->vars['showCheckboxes'] = $this->showCheckboxes;
        $this->vars['showSetup'] = $this->showSetup;
        $this->vars['showPagination'] = $this->showPagination;
        $this->vars['showPageNumbers'] = $this->showPageNumbers;
        $this->vars['showSorting'] = $this->showSorting;
        $this->vars['sortColumn'] = $this->getSortColumn();
        $this->vars['sortDirection'] = $this->sortDirection;

        if ($this->showPagination) {
            $this->vars['pageCurrent'] = $this->records->currentPage();

            // Store the currently visited page number in the session so the same
            // data can be displayed when the user returns to this list.
            $this->putSession('lastVisitedPage', $this->vars['pageCurrent']);

            if ($this->showPageNumbers) {
                $this->vars['recordTotal'] = $this->records->total();
                $this->vars['pageLast'] = $this->records->lastPage();
                $this->vars['pageFrom'] = $this->records->firstItem();
                $this->vars['pageTo'] = $this->records->lastItem();
            }
            else {
                $this->vars['hasMorePages'] = $this->records->hasMorePages();
            }
        }
        else {
            $this->vars['recordTotal'] = $this->records->count();
            $this->vars['pageCurrent'] = 1;
        }
    }

    /**
     * Event handler for refreshing the list.
     */
    public function onRefresh()
    {
        $this->prepareVars();

        return ['#'.$this->getId() => $this->makePartial('list')];
    }

    /**
     * Event handler for switching the page number.
     */
    public function onPaginate()
    {
        $this->currentPageNumber = post('page');

        return $this->onRefresh();
    }

    /**
     * Event handler for changing the filter
     */
    public function onFilter()
    {
        $this->currentPageNumber = 1;

        return $this->onRefresh();
    }

    /**
     * Validate the supplied form model.
     * @return void
     */
    protected function validateModel()
    {
        if (!$this->model) {
            throw new ApplicationException(Lang::get(
                'backend::lang.list.missing_model',
                ['class'=>get_class($this->controller)]
            ));
        }

        if (!$this->model instanceof Model) {
            throw new ApplicationException(Lang::get(
                'backend::lang.model.invalid_class',
                ['model'=>get_class($this->model), 'class'=>get_class($this->controller)]
            ));
        }

        return $this->model;
    }

    /**
     * Replaces the @ symbol with a table name in a model
     * @param  string $sql
     * @param  string $table
     * @return string
     */
    protected function parseTableName($sql, $table)
    {
        return str_replace('@', $table.'.', $sql);
    }

    /**
     * Applies any filters to the model.
     */
    public function prepareQuery()
    {
        $query = $this->model->newQuery();
        $primaryTable = $this->model->getTable();
        $selects = [$primaryTable.'.*'];
        $joins = [];
        $withs = [];
        $bindings = [];

        /**
         * @event backend.list.extendQueryBefore
         * Provides an opportunity to modify the `$query` object before the List widget applies its scopes to it.
         *
         * Example usage:
         *
         *     Event::listen('backend.list.extendQueryBefore', function ($listWidget, $query) {
         *         $query->whereNull('deleted_at');
         *     });
         *
         * Or
         *
         *     $listWidget->bindEvent('list.extendQueryBefore', function ($query) {
         *         $query->whereNull('deleted_at');
         *     });
         *
         */
        $this->fireSystemEvent('backend.list.extendQueryBefore', [$query]);

        /*
         * Prepare searchable column names
         */
        $primarySearchable = [];
        $relationSearchable = [];

        if (!empty($this->searchTerm) && ($searchableColumns = $this->getSearchableColumns())) {
            foreach ($searchableColumns as $column) {
                /*
                 * Related
                 */
                if ($this->isColumnRelated($column)) {
                    $table = $this->model->makeRelation($column->relation)->getTable();
                    $columnName = isset($column->sqlSelect)
                        ? DbDongle::raw($this->parseTableName($column->sqlSelect, $table))
                        : $table . '.' . $column->valueFrom;

                    $relationSearchable[$column->relation][] = $columnName;
                }
                /*
                 * Primary
                 */
                else {
                    $columnName = isset($column->sqlSelect)
                        ? DbDongle::raw($this->parseTableName($column->sqlSelect, $primaryTable))
                        : DbDongle::cast(Db::getTablePrefix() . $primaryTable . '.' . $column->columnName, 'TEXT');

                    $primarySearchable[] = $columnName;
                }
            }
        }

        /*
         * Prepare related eager loads (withs) and custom selects (joins)
         */
        foreach ($this->getVisibleColumns() as $column) {
            if ($column->useRelationCount()) {
                $query->withCount($column->relation);
            }

            if (!$this->isColumnRelated($column) || (!isset($column->sqlSelect) && !isset($column->valueFrom))) {
                continue;
            }

            if (isset($column->valueFrom)) {
                $withs[] = $column->relation;
            }

            $joins[] = $column->relation;
        }

        /*
         * Add eager loads to the query
         */
        if ($withs) {
            $query->with(array_unique($withs));
        }

        /*
         * Apply search term
         */
        $query->where(function ($innerQuery) use ($primarySearchable, $relationSearchable, $joins) {

            /*
             * Search primary columns
             */
            if (count($primarySearchable) > 0) {
                $this->applySearchToQuery($innerQuery, $primarySearchable, 'or');
            }

            /*
             * Search relation columns
             */
            if ($joins) {
                foreach (array_unique($joins) as $join) {
                    /*
                     * Apply a supplied search term for relation columns and
                     * constrain the query only if there is something to search for
                     */
                    $columnsToSearch = array_get($relationSearchable, $join, []);

                    if (count($columnsToSearch) > 0) {
                        $innerQuery->orWhereHas($join, function ($_query) use ($columnsToSearch) {
                            $this->applySearchToQuery($_query, $columnsToSearch);
                        });
                    }
                }
            }
        });

        /*
         * Custom select queries
         */
        foreach ($this->getVisibleColumns() as $column) {
            if (!isset($column->sqlSelect)) {
                continue;
            }

            $alias = $query->getQuery()->getGrammar()->wrap($column->columnName);

            /*
             * Relation column
             */
            if (isset($column->relation)) {
                // @todo Find a way...
                $relationType = $this->model->getRelationType($column->relation);
                if ($relationType == 'morphTo') {
                    throw new ApplicationException('The relationship morphTo is not supported for list columns.');
                }

                $table = $this->model->makeRelation($column->relation)->getTable();
                $sqlSelect = $this->parseTableName($column->sqlSelect, $table);

                /*
                 * Manipulate a count query for the sub query
                 */
                $relationObj = $this->model->{$column->relation}();
                $countQuery = $relationObj->getRelationExistenceQuery($relationObj->getRelated()->newQueryWithoutScopes(), $query);

                $joinSql = $this->isColumnRelated($column, true)
                    ? DbDongle::raw("group_concat(" . $sqlSelect . " separator ', ')")
                    : DbDongle::raw($sqlSelect);

                $joinSql = $countQuery->select($joinSql)->toSql();

                $selects[] = Db::raw("(".$joinSql.") as ".$alias);

                /*
                 * If this is a polymorphic relation there will be bindings that need to be added to the query
                 */
                $bindings = array_merge($bindings, $countQuery->getBindings());
            }
            /*
             * Primary column
             */
            else {
                $sqlSelect = $this->parseTableName($column->sqlSelect, $primaryTable);
                $selects[] = DbDongle::raw($sqlSelect . ' as '. $alias);
            }
        }

        /*
         * Apply sorting
         */
        if ($this->useSorting() && ($sortColumn = $this->getSortColumn())) {
            if (($column = array_get($this->allColumns, $sortColumn)) && $column->valueFrom) {
                $sortColumn = $this->isColumnPivot($column)
                    ? 'pivot_' . $column->valueFrom
                    : $column->valueFrom;
            }

            if ($column->useRelationCount()) {
                $sortColumn = $column->relation . '_count';
            }

            $query->orderBy($sortColumn, $this->sortDirection);
        }

        /*
         * Apply filters
         */
        foreach ($this->filterCallbacks as $callback) {
            $callback($query);
        }

        /*
         * Add custom selects
         */
        $query->addSelect($selects);

        /*
         * Add bindings for polymorphic relations
         */
        $query->addBinding($bindings, 'select');

        /**
         * @event backend.list.extendQuery
         * Provides an opportunity to modify and / or return the `$query` object after the List widget has applied its scopes to it and before it's used to get the records.
         *
         * Example usage:
         *
         *     Event::listen('backend.list.extendQuery', function ($listWidget, $query) {
         *         $newQuery = MyModel::newQuery();
         *         return $newQuery;
         *     });
         *
         * Or
         *
         *     $listWidget->bindEvent('list.extendQuery', function ($query) {
         *         $query->whereNull('deleted_at');
         *     });
         *
         */
        if ($event = $this->fireSystemEvent('backend.list.extendQuery', [$query])) {
            return $event;
        }

        return $query;
    }

    /**
     * Returns all the records from the supplied model, after filtering.
     * @return Collection
     */
    protected function getRecords()
    {
        $query = $this->prepareQuery();

        if ($this->showPagination) {
            $method = $this->showPageNumbers ? 'paginate' : 'simplePaginate';
            $currentPageNumber = $this->getCurrentPageNumber($query);
            $records = $query->{$method}($this->recordsPerPage, $currentPageNumber);
        }
        else {
            $records = $query->get();
        }

        /**
         * @event backend.list.extendRecords
         * Provides an opportunity to modify and / or return the `$records` Collection object before the widget uses it.
         *
         * Example usage:
         *
         *     Event::listen('backend.list.extendRecords', function ($listWidget, $records) {
         *         $model = MyModel::where('always_include', true)->first();
         *         $records->prepend($model);
         *     });
         *
         * Or
         *
         *     $listWidget->bindEvent('list.extendRecords', function ($records) {
         *         $model = MyModel::where('always_include', true)->first();
         *         $records->prepend($model);
         *     });
         *
         */
        if ($event = $this->fireSystemEvent('backend.list.extendRecords', [&$records])) {
            $records = $event;
        }

        return $this->records = $records;
    }

    /**
     * Returns the current page number for the list.
     *
     * This will override the current page number provided by the user if it is past the last page of available records.
     *
     * @param object $query
     * @return int
     */
    protected function getCurrentPageNumber($query)
    {
        // Restore the last visited page from the session if available.
        $currentPageNumber = $this->currentPageNumber;
        if (empty($currentPageNumber)) {
            $currentPageNumber = $this->getSession('lastVisitedPage');
        }

        $currentPageNumber = intval($currentPageNumber);

        if ($currentPageNumber > 1) {
            $count = $query->count();

            // If the current page number is higher than the amount of available pages, go to the last available page
            if ($count <= (($currentPageNumber - 1) * $this->recordsPerPage)) {
                $currentPageNumber = ceil($count / $this->recordsPerPage);
            }
        }

        return $currentPageNumber;
    }

    /**
     * hasRecordAction will specify is anything is clickable
     */
    public function hasRecordAction(): bool
    {
        return isset($this->recordOnClick) || isset($this->recordUrl);
    }

    /**
     * getRecordAction
     */
    public function getRecordAction($record): ?array
    {
        if (!$this->hasRecordAction()) {
            return null;
        }

        $url = $this->getRecordUrl($record);
        $onClick = $this->getRecordOnClick($record);

        /**
         * @event backend.list.overrideRecordAction
         * Overrides the record url or onclick event in a list widget.
         *
         * If a value is returned from this event, it will be used as the url for the provided record.
         * $url contains the default url and $record is a reference to the model instance.
         * Example usage:
         *
         *     Event::listen('backend.list.overrideRecordAction', function ($listWidget, $record, $url, $onClick) {
         *         if ($record->user_id !== BackendAuth::getUser()->id) {
         *             return 'acme/blog/posts/preview/' . $record->id;
         *         }
         *     });
         *
         * Or
         *
         *     $listWidget->bindEvent('list.overrideRecordAction', function ($record, $url, $onClick) {
         *         if ($record->user_id !== BackendAuth::getUser()->id) {
         *             return 'acme/blog/posts/preview/' . $record->id;
         *         }
         *     });
         *
         */
        if ($event = $this->fireSystemEvent('backend.list.overrideRecordAction', [$record, $url, $onClick])) {
            if (is_array($event)) {
                // Override onclick event
                if (array_key_exists('onclick', $event)) {
                    if ($event['onclick'] === null) {
                        $onClick = null;
                    }
                    else {
                        $onClick = Html::attributes(['onclick' => $event['onclick']]);
                    }
                }

                // Override URL
                if (array_key_exists('url', $event)) {
                    if ($event['url'] === null) {
                        $url = 'javascript:;';
                    }
                    else {
                        $url = Backend::url($event['url']);
                    }
                }

                // Override clickable entirely
                if (array_key_exists('clickable', $event) && $event['clickable'] === false) {
                    return null;
                }
            }
            elseif (is_string($event)) {
                $url = Backend::url($event);
            }
        }

        return [$url, $onClick];
    }

    /**
     * getRecordUrl returns the record URL address for a list row.
     * @param  Model $record
     * @return string
     */
    public function getRecordUrl($record)
    {
        if (isset($this->recordOnClick)) {
            return 'javascript:;';
        }

        if (!isset($this->recordUrl)) {
            return null;
        }

        $url = RouterHelper::replaceParameters($record, $this->recordUrl);

        return Backend::url($url);
    }

    /**
     * getRecordOnClick returns the onclick event for a list row.
     * @param  Model $record
     * @return string
     */
    public function getRecordOnClick($record)
    {
        if (!isset($this->recordOnClick)) {
            return null;
        }

        $recordOnClick = RouterHelper::replaceParameters($record, $this->recordOnClick);

        return Html::attributes(['onclick' => $recordOnClick]);
    }

    /**
     * getColumns gets all the registered columns for the instance.
     * @return array
     */
    public function getColumns()
    {
        return $this->allColumns ?: $this->defineListColumns();
    }

    /**
     * getColumn gets a specified column object
     * @param  string $column
     * @return mixed
     */
    public function getColumn($column)
    {
        if (!isset($this->allColumns[$column])) {
            throw new ApplicationException('No definition for column ' . $column);
        }

        return $this->allColumns[$column];
    }

    /**
     * isColumnVisible checks if a column is visible to the list
     */
    public function isColumnVisible(string $columnName): bool
    {
        $columns = $this->visibleColumns ?: $this->getVisibleColumns();

        return isset($columns[$columnName]);
    }

    /**
     * getVisibleColumns returns the list columns that are visible by list settings or default
     */
    public function getVisibleColumns()
    {
        $definitions = $this->defineListColumns();
        $columns = [];

        /*
         * Supplied column list
         */
        if ($this->showSetup && $this->columnOverride === null) {
            $this->columnOverride = $this->getUserPreference('visible', null);
        }

        if ($this->columnOverride && is_array($this->columnOverride)) {
            $invalidColumns = array_diff($this->columnOverride, array_keys($definitions));
            if (!count($definitions)) {
                throw new ApplicationException(Lang::get(
                    'backend::lang.list.missing_column',
                    ['columns'=>implode(',', $invalidColumns)]
                ));
            }

            $availableColumns = array_intersect($this->columnOverride, array_keys($definitions));
            foreach ($availableColumns as $columnName) {
                $definitions[$columnName]->invisible = false;
                $columns[$columnName] = $definitions[$columnName];
            }
        }
        /*
         * Use default column list
         */
        else {
            foreach ($definitions as $columnName => $column) {
                if ($column->invisible) {
                    continue;
                }

                $columns[$columnName] = $definitions[$columnName];
            }
        }

        return $this->visibleColumns = $columns;
    }

    /**
     * defineColumn
     */
    public function defineColumn(string $columnName = null, string $label = null): ColumnDefinition
    {
        return $this->allColumns[$columnName] = new ListColumn($columnName, $label);
    }

    /**
     * defineListColumns builds an array of list columns with keys as the column name
     * and values as a ListColumn object
     */
    protected function defineListColumns(): array
    {
        if ($this->model && method_exists($this->model, 'defineListColumns')) {
            $this->model->defineListColumns($this);
        }

        $hasColumns = ($this->columns && is_array($this->columns)) || $this->allColumns;

        if (!$hasColumns) {
            $class = get_class($this->model instanceof Model ? $this->model : $this->controller);
            throw new ApplicationException(Lang::get('backend::lang.list.missing_columns', compact('class')));
        }

        if ($this->columns) {
            $this->addColumns($this->columns);
        }

        /**
         * @event backend.list.extendColumns
         * Provides an opportunity to modify the columns of a List widget
         *
         * Example usage:
         *
         *     Event::listen('backend.list.extendColumns', function ($listWidget) {
         *         // Only for the User controller
         *         if (!$listWidget->getController() instanceof \Backend\Controllers\Users) {
         *             return;
         *         }
         *
         *         // Only for the User model
         *         if (!$listWidget->model instanceof \Backend\Models\User) {
         *             return;
         *         }
         *
         *         // Add an extra birthday column
         *         $listWidget->addColumns([
         *             'birthday' => [
         *                 'label' => 'Birthday'
         *             ]
         *         ]);
         *
         *         // Remove a Surname column
         *         $listWidget->removeColumn('surname');
         *     });
         *
         * Or
         *
         *     $listWidget->bindEvent('list.extendColumns', function () use ($listWidget) {
         *         // Only for the User controller
         *         if (!$listWidget->getController() instanceof \Backend\Controllers\Users) {
         *             return;
         *         }
         *
         *         // Only for the User model
         *         if (!$listWidget->model instanceof \Backend\Models\User) {
         *             return;
         *         }
         *
         *         // Add an extra birthday column
         *         $listWidget->addColumns([
         *             'birthday' => [
         *                 'label' => 'Birthday'
         *             ]
         *         ]);
         *
         *         // Remove a Surname column
         *         $listWidget->removeColumn('surname');
         *     });
         *
         */
        $this->fireSystemEvent('backend.list.extendColumns');

        /*
         * Purge hidden columns
         */
        foreach ($this->allColumns as $key => $column) {
            if ($column->hidden) {
                unset($this->allColumns[$key]);
            }
        }

        /*
         * Use a supplied column order
         */
        if ($columnOrder = $this->getUserPreference('order', null)) {
            $orderedDefinitions = [];
            foreach ($columnOrder as $column) {
                if (isset($this->allColumns[$column])) {
                    $orderedDefinitions[$column] = $this->allColumns[$column];
                }
            }

            $this->allColumns = array_merge($orderedDefinitions, $this->allColumns);
        }

        return $this->allColumns;
    }

    /**
     * Programatically add columns, used internally and for extensibility.
     * @param array $columns Column definitions
     */
    public function addColumns(array $columns)
    {
        /*
         * Build a final collection of list column objects
         */
        foreach ($columns as $columnName => $config) {
            // Check if user has permissions to show this column
            $permissions = array_get($config, 'permissions');
            if (!empty($permissions) && !BackendAuth::getUser()->hasAccess($permissions, false)) {
                continue;
            }

            $this->allColumns[$columnName] = $this->makeListColumn($columnName, $config);
        }
    }

    /**
     * Programatically remove a column, used for extensibility.
     * @param string $column Column name
     */
    public function removeColumn($columnName)
    {
        if (isset($this->allColumns[$columnName])) {
            unset($this->allColumns[$columnName]);
        }
    }

    /**
     * Creates a list column object from it's name and configuration.
     */
    protected function makeListColumn($name, $config)
    {
        if (is_string($config)) {
            $label = $config;
        }
        elseif (isset($config['label'])) {
            $label = $config['label'];
        }
        else {
            $label = studly_case($name);
        }

        /*
         * Auto configure pivot relation
         */
        if (starts_with($name, 'pivot[') && strpos($name, ']') !== false) {
            $_name = HtmlHelper::nameToArray($name);
            $relationName = array_shift($_name);
            $valueFrom = array_shift($_name);

            if (count($_name) > 0) {
                $valueFrom  .= '['.implode('][', $_name).']';
            }

            $config['relation'] = $relationName;
            $config['valueFrom'] = $valueFrom;
            $config['searchable'] = false;
        }
        /*
         * Auto configure standard relation
         */
        elseif (strpos($name, '[') !== false && strpos($name, ']') !== false) {
            $config['valueFrom'] = $name;
            $config['sortable'] = false;
            $config['searchable'] = false;
        }

        $columnType = $config['type'] ?? null;

        $column = new ListColumn($name, $label);

        if ($config) {
            $column->useConfig($config);
        }

        if ($columnType) {
            $column->displayAs($columnType);
        }

        return $column;
    }

    /**
     * Calculates the total columns used in the list, including checkboxes
     * and other additions.
     */
    protected function getTotalColumns()
    {
        $columns = $this->visibleColumns ?: $this->getVisibleColumns();
        $total = count($columns);

        if ($this->showCheckboxes) {
            $total++;
        }

        if ($this->showSetup) {
            $total++;
        }

        return $total;
    }

    /**
     * Looks up the column header
     */
    public function getHeaderValue($column)
    {
        $value = Lang::get($column->label);

        /**
         * @event backend.list.overrideHeaderValue
         * Overrides the column header value in a list widget.
         *
         * If a value is returned from this event, it will be used as the value for the provided column.
         * `$value` is passed by reference so modifying the variable in place is also supported. Example usage:
         *
         *     Event::listen('backend.list.overrideHeaderValue', function ($listWidget, $column, &$value) {
         *         $value .= '-modified';
         *     });
         *
         * Or
         *
         *     $listWidget->bindEvent('list.overrideHeaderValue', function ($column, $value) {
         *         return 'Custom header value';
         *     });
         *
         */
        if ($response = $this->fireSystemEvent('backend.list.overrideHeaderValue', [$column, &$value])) {
            $value = $response;
        }

        return $value;
    }

    /**
     * getColumnValueRaw returns a raw column value
     * @return string
     */
    public function getColumnValueRaw($record, $column)
    {
        $columnName = $column->columnName;

        /*
         * Handle taking value from model relation.
         */
        if ($column->valueFrom && $column->relation) {
            $columnName = $column->relation;

            if (!array_key_exists($columnName, $record->getRelations())) {
                $value = null;
            }
            elseif ($this->isColumnRelated($column, true)) {
                $value = $record->{$columnName}->pluck($column->valueFrom)->all();
            }
            elseif ($this->isColumnRelated($column) || $this->isColumnPivot($column)) {
                $value = $record->{$columnName}
                    ? $column->getValueFromData($record->{$columnName})
                    : null;
            }
            else {
                $value = null;
            }
        }
        /*
         * Handle taking value from model attribute.
         */
        elseif ($column->valueFrom) {
            $value = $column->getValueFromData($record);
        }
        /*
         * Otherwise, if the column is a relation, it will be a custom select,
         * so prevent the Model from attempting to load the relation
         * if the value is NULL.
         */
        else {
            if (
                $record->hasRelation($columnName) &&
                array_key_exists($columnName, $record->attributes)
            ) {
                $value = $record->attributes[$columnName];
            }
            elseif ($column->useRelationCount()) {
                $countColumnName = Str::snake($column->relation) . '_count';
                $value = $record->{$countColumnName};
            }
            else {
                $value = $record->{$columnName};
            }
        }

        /**
         * @event backend.list.overrideColumnValueRaw
         * Overrides the raw column value in a list widget.
         *
         * If a value is returned from this event, it will be used as the raw value for the provided column.
         * `$value` is passed by reference so modifying the variable in place is also supported. Example usage:
         *
         *     Event::listen('backend.list.overrideColumnValueRaw', function ($listWidget, $record, $column, &$value) {
         *         $value .= '-modified';
         *     });
         *
         * Or
         *
         *     $listWidget->bindEvent('list.overrideColumnValueRaw', function ($record, $column, $value) {
         *         return 'No values for you!';
         *     });
         *
         */
        if ($response = $this->fireSystemEvent('backend.list.overrideColumnValueRaw', [$record, $column, &$value])) {
            $value = $response;
        }

        return $value;
    }

    /**
     * getColumnValue returns a column value, with filters applied
     */
    public function getColumnValue($record, $column)
    {
        /*
         * Custom display attribute that pulls directly from the model
         */
        if ($column->displayFrom) {
            $columnName = $column->displayFrom;
            $value = $record->{$columnName};
        }
        /*
         * Standard value
         */
        else {
            $value = $this->getColumnValueRaw($record, $column);
        }

        /*
         * Apply filters
         */
        if (method_exists($this, 'eval'. studly_case($column->type) .'TypeValue')) {
            $value = $this->{'eval'. studly_case($column->type) .'TypeValue'}($record, $column, $value);
        }
        else {
            $value = $this->evalCustomListType($column->type, $record, $column, $value);
        }

        /*
         * Apply default value.
         */
        if ($value === '' || $value === null) {
            $value = $column->defaults;
        }

        /**
         * @event backend.list.overrideColumnValue
         * Overrides the column value in a list widget.
         *
         * If a value is returned from this event, it will be used as the value for the provided column.
         * `$value` is passed by reference so modifying the variable in place is also supported. Example usage:
         *
         *     Event::listen('backend.list.overrideColumnValue', function ($listWidget, $record, $column, &$value) {
         *         $value .= '-modified';
         *     });
         *
         * Or
         *
         *     $listWidget->bindEvent('list.overrideColumnValue', function ($record, $column, $value) {
         *         return 'No values for you!';
         *     });
         *
         */
        if ($response = $this->fireSystemEvent('backend.list.overrideColumnValue', [$record, $column, &$value])) {
            $value = $response;
        }

        return $value;
    }

    /**
     * getCheckedRows
     */
    protected function getCheckedRows(): array
    {
        return array_unique((array) post('allChecked'));
    }

    /**
     * getCheckedRowsEncoded
     */
    protected function getCheckedRowsEncoded(): string
    {
        try {
            return json_encode(array_values($this->getCheckedRows()));
        }
        catch (Exception $ex) {
            return '';
        }
    }

    /**
     * isRowChecked
     */
    protected function isRowChecked($record): bool
    {
        return in_array($record->getKey(), $this->getCheckedRows());
    }

    /**
     * Adds a custom CSS class string to a record row
     * @param  Model $record Populated model
     * @return string
     */
    public function getRowClass($record)
    {
        $value = '';

        /**
         * @event backend.list.injectRowClass
         * Provides opportunity to inject a custom CSS row class
         *
         * If a value is returned from this event, it will be used as the value for the row class.
         * `$value` is passed by reference so modifying the variable in place is also supported. Example usage:
         *
         *     Event::listen('backend.list.injectRowClass', function ($listWidget, $record, &$value) {
         *         $value .= '-modified';
         *     });
         *
         * Or
         *
         *     $listWidget->bindEvent('list.injectRowClass', function ($record, $value) {
         *         return 'strike';
         *     });
         *
         */
        if ($response = $this->fireSystemEvent('backend.list.injectRowClass', [$record, &$value])) {
            $value = $response;
        }

        return $value;
    }

    /**
     * getColumnTimezonePreference for date specific columns
     */
    protected function getColumnTimezonePreference($column, $default = true): bool
    {
        // @deprecated API
        if (!empty($column->config['ignoreTimezone'])) {
            return false;
        }

        return (bool) $column->getConfig('useTimezone', $default);
    }

    //
    // Value processing
    //

    /**
     * Process a custom list types registered by plugins.
     */
    protected function evalCustomListType($type, $record, $column, $value)
    {
        $plugins = PluginManager::instance()->getRegistrationMethodValues('registerListColumnTypes');

        foreach ($plugins as $availableTypes) {
            if (!isset($availableTypes[$type])) {
                continue;
            }

            $callback = $availableTypes[$type];

            if (is_callable($callback)) {
                return call_user_func_array($callback, [$value, $column, $record]);
            }
        }

        $customMessage = '';
        if ($type === 'relation') {
            $customMessage = 'Type: relation is not supported, instead use the relation property to specify a relationship to pull the value from and set the type to the type of the value expected.';
        }

        throw new ApplicationException(sprintf('List column type "%s" could not be found. %s', $type, $customMessage));
    }

    /**
     * evalTextTypeValue as text and escape the value
     * @return string
     */
    protected function evalTextTypeValue($record, $column, $value)
    {
        if (is_array($value) && count($value) === count($value, COUNT_RECURSIVE)) {
            $value = implode(', ', $value);
        }

        if (is_string($column->format) && !empty($column->format)) {
            $value = sprintf($column->format, $value);
        }

        return htmlentities($value, ENT_QUOTES, 'UTF-8', false);
    }

    /**
     * evalNumberTypeValue process as number, proxy to text but uses different styling
     * @return string
     */
    protected function evalNumberTypeValue($record, $column, $value)
    {
        return $this->evalTextTypeValue($record, $column, $value);
    }

    /**
     * evalImageTypeValue will process an image value
     * @return string
     */
    protected function evalImageTypeValue($record, $column, $value)
    {
        $config = $column->config;
        $width = isset($config['width']) ? $config['width'] : 68;
        $height = isset($config['height']) ? $config['height'] : 68;
        $options = isset($config['options']) ? $config['options'] : [];
        $isDefaultSize = !isset($config['width']) && !isset($config['height']);

        $colName = $column->columnName;
        $image = null;

        if (isset($record->attachMany[$colName])) {
            $image = $value->first();
        }
        elseif (isset($record->attachOne[$colName])) {
            $image = $value;
        }
        elseif (strpos($value, '://') !== false) {
            $image = $value;
        }
        elseif (strlen($value)) {
            $image = \Media\Classes\MediaLibrary::url($value);
        }

        if (!$image) {
            return '';
        }

        $imageUrl = \System\Classes\ResizeImages::resize($image, $width, $height, $options);

        return $this->makePartial('column_image', [
            'imageUrl' => $imageUrl,
            'isDefaultSize' => $isDefaultSize,
            'width' => $width,
            'height' => $height
        ]);
    }

    /**
     * evalSwitchTypeValue as boolean switch
     */
    protected function evalSwitchTypeValue($record, $column, $value)
    {
        $contents = '';
        $config = $column->config;

        if ($value) {
            $contents = Lang::get($config['options'][1] ?? 'backend::lang.list.column_switch_true');
        }
        else {
            $contents = Lang::get($config['options'][0] ?? 'backend::lang.list.column_switch_false');
        }

        return $contents;
    }

    /**
     * evalSummaryTypeValue will limit a value by words
     */
    protected function evalSummaryTypeValue($record, $column, $value)
    {
        $config = $column->config;
        $endChars = isset($config['endChars']) ? $config['endChars'] : '...';
        $limitChars = isset($config['limitChars']) ? $config['limitChars'] : 40;
        $limitWords = isset($config['limitWords']) ? $config['limitWords'] : null;

        // Collapse spacing for inline nodes that will get stripped
        // "Welcome <img />, User" should read "Welcome, User"
        $result = $value;
        $result = str_replace(' <', '<', $result);

        // Add natural spacing between HTML nodes
        $result = str_replace("><", '> <', $value);

        // Strip HTML
        $result = $original = trim(Html::strip($result));

        // Nothing left
        if (!strlen($result)) {
            return $result;
        }

        // Limit by chars and estimate word count
        if (!$limitWords) {
            $limitStr = Str::limit($result, $limitChars, '');
            $limitWords = substr_count($limitStr, ' ') + 1;
        }

        // Strip HTML, limit to words
        $result = Str::words($result, $limitWords, '');

        // Add end suffix where original differs
        if (mb_strlen($result) !== mb_strlen($original)) {
            $result .= $endChars;
        }

        return $result;
    }

    /**
     * evalDatetimeTypeValue as a datetime value
     */
    protected function evalDatetimeTypeValue($record, $column, $value)
    {
        if ($value === null) {
            return null;
        }

        $dateTime = $this->validateDateTimeValue($value, $column);

        if ($column->format !== null) {
            $value = $dateTime->format($column->format);
        }
        else {
            $value = $dateTime->toDayDateTimeString();
        }

        $options = [
            'defaultValue' => $value,
            'format' => $column->format,
            'formatAlias' => 'dateTimeLongMin',
            'useTimezone' => $this->getColumnTimezonePreference($column),
        ];

        return Backend::dateTime($dateTime, $options);
    }

    /**
     * evalTimeTypeValue as a time value
     */
    protected function evalTimeTypeValue($record, $column, $value)
    {
        if ($value === null) {
            return null;
        }

        $dateTime = $this->validateDateTimeValue($value, $column);

        $format = $column->format ?? 'g:i A';

        $value = $dateTime->format($format);

        $options = [
            'defaultValue' => $value,
            'format' => $column->format,
            'formatAlias' => 'time',
            'useTimezone' => $this->getColumnTimezonePreference($column, false),
        ];

        return Backend::dateTime($dateTime, $options);
    }

    /**
     * evalDateTypeValue as a date value
     */
    protected function evalDateTypeValue($record, $column, $value)
    {
        if ($value === null) {
            return null;
        }

        $dateTime = $this->validateDateTimeValue($value, $column);

        if ($column->format !== null) {
            $value = $dateTime->format($column->format);
        }
        else {
            $value = $dateTime->toFormattedDateString();
        }

        $options = [
            'defaultValue' => $value,
            'format' => $column->format,
            'formatAlias' => 'dateLongMin',
            'useTimezone' => $this->getColumnTimezonePreference($column, false),
        ];

        return Backend::dateTime($dateTime, $options);
    }

    /**
     * evalTimesinceTypeValue as diff for humans (1 min ago)
     */
    protected function evalTimesinceTypeValue($record, $column, $value)
    {
        if ($value === null) {
            return null;
        }

        $dateTime = $this->validateDateTimeValue($value, $column);

        $value = DateTimeHelper::timeSince($dateTime);

        $options = [
            'defaultValue' => $value,
            'timeSince' => true,
            'useTimezone' => $this->getColumnTimezonePreference($column),
        ];

        return Backend::dateTime($dateTime, $options);
    }

    /**
     * evalTimetenseTypeValue as time as current tense (Today at 0:00)
     */
    protected function evalTimetenseTypeValue($record, $column, $value)
    {
        if ($value === null) {
            return null;
        }

        $dateTime = $this->validateDateTimeValue($value, $column);

        $value = DateTimeHelper::timeTense($dateTime);

        $options = [
            'defaultValue' => $value,
            'timeTense' => true,
            'useTimezone' => $this->getColumnTimezonePreference($column),
        ];

        return Backend::dateTime($dateTime, $options);
    }

    /**
     * evalSelectableTypeValue processes as selectable value types for 'dropdown',
     * 'radio', 'balloon-selector' and similar form field types
     */
    protected function evalSelectableTypeValue($record, $column, $value)
    {
        $formField = new \Backend\Classes\FormField($column->columnName, $column->label);

        $fieldOptions = $column->config['options'] ?? null;

        if (!is_array($fieldOptions)) {
            $model = $this->isColumnRelated($column)
                ? $this->model->makeRelation($column->relation)
                : $this->model;

            $fieldOptions = $formField->getOptionsFromModel(
                $model,
                $fieldOptions,
                $record->toArray()
            );
        }

        return $this->makePartial('column_selectable', [
            'fieldOptions' => $fieldOptions,
            'value' => $value
        ]);
    }

    /**
     * evalPartialTypeValue as partial reference
     */
    protected function evalPartialTypeValue($record, $column, $value)
    {
        return $this->controller->makePartial($column->path ?: $column->columnName, [
            'listColumn' => $column,
            'listRecord' => $record,
            'listValue' => $value,
            'column' => $column,
            'record' => $record,
            'value' => $value
        ]);
    }

    /**
     * evalColorPickerTypeValue as background color, to be seen at list
     */
    protected function evalColorPickerTypeValue($record, $column, $value)
    {
        return $this->makePartial('column_colorpicker', [
            'value' => $value
        ]);
    }

    /**
     * validateDateTimeValue column type
     */
    protected function validateDateTimeValue($value, $column)
    {
        $value = DateTimeHelper::makeCarbon($value, false);

        if (!$value instanceof Carbon) {
            throw new ApplicationException(Lang::get(
                'backend::lang.list.invalid_column_datetime',
                ['column' => $column->columnName]
            ));
        }

        return $value;
    }

    //
    // Filtering
    //

    /**
     * addFilter query to the stack.
     */
    public function addFilter(callable $filter)
    {
        $this->filterCallbacks[] = $filter;
    }

    //
    // Searching
    //

    /**
     * setSearchTerm applies a search term to the list results, searching will disable tree
     * view if a value is supplied.
     * @param string $term
     * @param boolean $resetPagination
     */
    public function setSearchTerm($term, $resetPagination = false)
    {
        // Reset pagination
        if ($resetPagination) {
            $this->currentPageNumber = 1;
        }

        // Set the actual term
        $this->searchTerm = $term;
    }

    /**
     * setSearchOptions applies a search options to the list search.
     * @param array $options
     */
    public function setSearchOptions($options = [])
    {
        extract(array_merge([
            'mode' => null,
            'scope' => null
        ], $options));

        $this->searchMode = $mode;
        $this->searchScope = $scope;
    }

    /**
     * getSearchableColumns returns a collection of columns which can be searched.
     * @return array
     */
    protected function getSearchableColumns()
    {
        $columns = $this->getColumns();
        $searchable = [];

        foreach ($columns as $column) {
            if (!$column->searchable) {
                continue;
            }

            $searchable[] = $column;
        }

        return $searchable;
    }

    /**
     * applySearchToQuery applies the search constraint to a query.
     */
    protected function applySearchToQuery($query, $columns, $boolean = 'and')
    {
        $term = $this->searchTerm;

        if ($scopeMethod = $this->searchScope) {
            $searchMethod = $boolean == 'and' ? 'where' : 'orWhere';
            $query->$searchMethod(function ($q) use ($term, $columns, $scopeMethod) {
                $q->$scopeMethod($term, $columns);
            });
        }
        else {
            $searchMethod = $boolean == 'and' ? 'searchWhere' : 'orSearchWhere';
            $query->$searchMethod($term, $columns, $this->searchMode);
        }
    }

    //
    // Sorting
    //

    /**
     * Event handler for sorting the list.
     */
    public function onSort()
    {
        if ($column = post('sortColumn')) {
            /*
             * Toggle the sort direction and set the sorting column
             */
            $sortOptions = ['column' => $this->getSortColumn(), 'direction' => $this->sortDirection];

            if ($column != $sortOptions['column'] || $sortOptions['direction'] == 'asc') {
                $this->sortDirection = $sortOptions['direction'] = 'desc';
            }
            else {
                $this->sortDirection = $sortOptions['direction'] = 'asc';
            }

            $this->sortColumn = $sortOptions['column'] = $column;

            /*
             * Persist the page number
             */
            $this->currentPageNumber = post('page');

            /*
             * Attempt a refresh with the new sortOptions and update the
             * the user session only if the query succeeded.
             */
            $result = $this->onRefresh();

            $this->putSession('sort', $sortOptions);

            return $result;
        }
    }

    /**
     * Returns the current sorting column, saved in a session or cached.
     */
    public function getSortColumn()
    {
        if (!$this->isSortable()) {
            return false;
        }

        if ($this->sortColumn !== null) {
            return $this->sortColumn;
        }

        /*
         * User preference
         */
        if ($this->showSorting && ($sortOptions = $this->getSession('sort'))) {
            $this->sortColumn = $sortOptions['column'];
            $this->sortDirection = $sortOptions['direction'];
        }

        /*
         * Supplied default
         */
        else {
            if (is_string($this->defaultSort)) {
                $this->sortColumn = $this->defaultSort;
                $this->sortDirection = 'desc';
            }
            elseif (is_array($this->defaultSort) && isset($this->defaultSort['column'])) {
                $this->sortColumn = $this->defaultSort['column'];
                $this->sortDirection = $this->defaultSort['direction'] ?? 'desc';
            }
        }

        /*
         * First available column
         */
        if ($this->sortColumn === null || !$this->isSortable($this->sortColumn)) {
            $columns = $this->visibleColumns ?: $this->getVisibleColumns();
            $columns = array_filter($columns, function ($column) {
                return $column->sortable;
            });
            $this->sortColumn = key($columns);
            $this->sortDirection = 'desc';
        }

        return $this->sortColumn;
    }

    /**
     * useSorting
     */
    protected function useSorting(): bool
    {
        return true;
    }

    /**
     * Returns the current sort direction or default of 'asc'
     */
    public function getSortDirection()
    {
        return $this->sortDirection ?? 'asc';
    }

    /**
     * Returns true if the column can be sorted.
     */
    protected function isSortable($column = null)
    {
        if ($column === null) {
            return (count($this->getSortableColumns()) > 0);
        }

        return array_key_exists($column, $this->getSortableColumns());
    }

    /**
     * Returns a collection of columns which are sortable.
     */
    protected function getSortableColumns()
    {
        if ($this->sortableColumns !== null) {
            return $this->sortableColumns;
        }

        $columns = $this->getColumns();
        $sortable = array_filter($columns, function ($column) {
            return $column->sortable;
        });

        return $this->sortableColumns = $sortable;
    }

    //
    // List Setup
    //

    /**
     * Event handler to display the list set up.
     */
    public function onLoadSetup()
    {
        $this->vars['columns'] = $this->getSetupListColumns();
        $this->vars['perPageOptions'] = $this->getSetupPerPageOptions();
        $this->vars['recordsPerPage'] = $this->recordsPerPage;
        return $this->makePartial('setup_form');
    }

    /**
     * Event handler to apply the list set up.
     */
    public function onApplySetup()
    {
        if (($visibleColumns = post('visible_columns')) && is_array($visibleColumns)) {
            $this->columnOverride = $visibleColumns;
            $this->putUserPreference('visible', $this->columnOverride);
        }

        $this->recordsPerPage = post('records_per_page', $this->recordsPerPage);

        $this->putUserPreference('order', post('column_order'));

        $this->putUserPreference('per_page', $this->recordsPerPage);

        return $this->onRefresh();
    }

    /**
     * Event handler to apply the list set up.
     */
    public function onResetSetup()
    {
        $this->resetUserPreference('visible');

        $this->resetUserPreference('per_page');

        $this->resetUserPreference('order');

        return $this->onRefresh();
    }

    /**
     * Returns an array of allowable records per page.
     */
    protected function getSetupPerPageOptions()
    {
        $perPageOptions = is_array($this->perPageOptions)
            ? $this->perPageOptions
            : [20, 40, 80, 100, 120];

        if (!in_array($this->recordsPerPage, $perPageOptions)) {
            $perPageOptions[] = $this->recordsPerPage;
        }

        sort($perPageOptions);

        return $perPageOptions;
    }

    /**
     * Returns all the list columns used for list set up.
     */
    protected function getSetupListColumns()
    {
        /*
         * Force all columns invisible
         */
        $columns = $this->defineListColumns();
        foreach ($columns as $column) {
            $column->invisible = true;
        }

        return array_merge($columns, $this->getVisibleColumns());
    }

    //
    // Helpers
    //

    /**
     * isColumnRelated checks if column refers to a relation of the model
     * @param ListColumn $column
     */
    protected function isColumnRelated($column, bool $isMulti = false): bool
    {
        if (!isset($column->relation) || $this->isColumnPivot($column)) {
            return false;
        }

        if (!$this->model->hasRelation($column->relation)) {
            throw new ApplicationException(Lang::get(
                'backend::lang.model.missing_relation',
                ['class'=>get_class($this->model), 'relation'=>$column->relation]
            ));
        }

        if (!$isMulti) {
            return true;
        }

        $relationType = $this->model->getRelationType($column->relation);

        return in_array($relationType, [
            'hasMany',
            'belongsToMany',
            'morphToMany',
            'morphedByMany',
            'morphMany',
            'attachMany',
            'hasManyThrough'
        ]);
    }

    /**
     * isColumnPivot checks if a column refers to a pivot model specifically.
     * @param  ListColumn  $column List column object
     */
    protected function isColumnPivot($column): bool
    {
        if (!isset($column->relation) || $column->relation !== 'pivot') {
            return false;
        }

        return true;
    }
}
