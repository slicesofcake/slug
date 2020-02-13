<?php
namespace Slug\Model\Behavior;

use Cake\Datasource\EntityInterface;
use Cake\Event\Event;
use Cake\ORM\Behavior;
use Cake\ORM\Query;
use Cake\Utility\Text;
use Slug\Exception\FieldException;
use Slug\Exception\FieldTypeException;
use Slug\Exception\IncrementException;
use Slug\Exception\LengthException;
use Slug\Exception\LimitException;
use Slug\Exception\MethodException;

class SlugBehavior extends Behavior
{

    /**
     * Default config.
     *
     * @var array
     */
    public $defaultConfig = [
        'source' => 'name',
        'replacement' => '-',
        'finder' => 'list',
        'length' => 255,
    ];

    /**
     * {@inheritdoc}
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        if (empty($this->getConfig())) {
            $this->setConfig('slug', $this->defaultConfig);
        }

        foreach ($this->getConfig() as $target => $config) {
            if (!is_array($config)) {
                $this->_configDelete($target);

                $target = $config;

                $this->setConfig($target, $this->defaultConfig);
            } else {
                $this->setConfig($target, array_merge($this->defaultConfig, $config));
            }

            if (!$this->getTable()->hasField($target)) {
                throw new FieldException(__d('slug', 'Cannot find target {0} field in schema.', $target));
            } elseif (!$this->getTable()->hasField($this->getConfig($target . '.source'))) {
                throw new FieldException(__d('slug', 'Cannot find source {0} field in schema.', $this->getConfig($target . '.source')));
            }

            if ($this->getTable()->getSchema()->getColumnType($target) !== 'string') {
                throw new FieldTypeException(__d('slug', 'Target field {0} should be string type.', $target));
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function beforeFind(Event $event, Query $query, $options)
    {
        $config = $this->getConfig();

        if (is_array($config)) {
            $query->select(array_keys($config));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function beforeSave(Event $event, EntityInterface $entity)
    {
        foreach ($this->getConfig() as $target => $config) {
            if (!isset($config['present']) || $config['present'] !== true) {
                $entity->{$target} = $this->createSlug($entity, $target);
            }
        }
    }

    /**
     * Create slug.
     *
     * @param EntityInterface $entity Entity.
     * @param string $target Target slug field name.
     * @return string Slug.
     */
    public function createSlug(EntityInterface $entity, string $target): string
    {
        $config = $this->getConfig($target);

        if ($entity->isDirty($config['source'])) {
            if ((mb_strlen($config['replacement']) + 1) < $config['length']) {
                if (isset($config['method'])) {
                    if (method_exists($this->getTable(), $config['method'])) {
                        $slug = $this->getTable()->{$config['method']}($entity, $config);
                    } else {
                        throw new MethodException(__d('slug', 'Method {0} does not exist.', $config['method']));
                    }
                } else {
                    $slug = Text::slug(mb_strtolower($entity->{$config['source']}), [
                        'replacement' => $config['replacement'],
                    ]);
                }

                $slugs = $this->sortSlugs($this->getSlugs($slug, $target));

                // Slug is just numbers
                if (preg_match('/^[0-9]+$/', $slug)) {
                    $numbers = preg_grep('/^[0-9]+$/', $slugs);

                    if (!empty($numbers)) {
                        sort($numbers);

                        $slug = end($numbers);

                        $slug++;
                    }
                }

                // Cut slug
                if (mb_strlen($replace = preg_replace('/\s+/', $config['replacement'], $slug)) > $config['length']) {
                    $slug = mb_substr($replace, 0, $config['length']);

                    // Update slug list based on cut slug
                    $slugs = $this->sortSlugs($this->getSlugs($slug, $target));
                }

                $slug = preg_replace('/' . preg_quote($config['replacement']) . '$/', '', trim(mb_substr($slug, 0, $config['length'])));

                if (in_array($slug, $slugs)) {
                    $list = preg_grep('/^' . preg_replace('/' . preg_quote($config['replacement']) . '([1-9]{1}[0-9]*)$/', $config['replacement'], $slug) . '/', $slugs);

                    preg_match('/^(.*)' . preg_quote($config['replacement']) . '([1-9]{1}[0-9]*)$/', end($list), $matches);

                    if (empty($matches)) {
                        $increment = 1;
                    } else {
                        if (isset($matches[2])) {
                            $increment = $matches[2] += 1;
                        } else {
                            throw new IncrementException(__d('slug', 'Cannot create next suffix because matches are empty.'));
                        }
                    }

                    if (mb_strlen($slug . $config['replacement'] . $increment) <= $config['length']) {
                        $string = $slug;
                    } elseif (mb_strlen(mb_substr($slug, 0, -mb_strlen($increment))) + mb_strlen($config['replacement'] . $increment) <= $config['length']) {
                        $string = mb_substr($slug, 0, $config['length'] - mb_strlen($config['replacement'] . $increment));
                    } else {
                        $string = mb_substr($slug, 0, -(mb_strlen($config['replacement'] . $increment)));
                    }

                    if (mb_strlen($string) > 0) {
                        $slug = $string . $config['replacement'] . $increment;

                        // Refresh slugs list
                        $slugs = $this->sortSlugs(array_merge($slugs, $this->getSlugs($slug, $target)));

                        if (in_array($slug, $slugs)) {
                            return $this->createSlug($slug, $target);
                        }
                    } else {
                        throw new LengthException(__d('slug', 'Cannot create slug because there are no available names.'));
                    }
                }

                return $slug;
            } else {
                throw new LimitException(__d('slug', 'Limit of length in {0} field is too short.', $target));
            }
        } else {
            return $entity->{$target};
        }
    }

    /**
     * Get existing slug list.
     *
     * @param string $slug Slug to find.
     * @param string $target Target slug field name.
     * @return array List of slugs.
     */
    protected function getSlugs(string $slug, string $target): array
    {
        return $this->getTable()->find($this->getConfig($target . '.finder'), [
            'valueField' => $target,
        ])->where([
            'OR' => [
                $this->getTable()->getAlias() . '.' . $target => $slug,
                $this->getTable()->getAlias() . '.' . $target . ' REGEXP' => '^' . preg_replace('/' . preg_quote($this->getConfig($target . '.replacement')) . '([1-9]{1}[0-9]*)$/', '', $slug),
            ],
        ])->order([
            $this->getTable()->getAlias() . '.' . $target => 'ASC',
        ])->toArray();
    }

    /**
     * Sort slug list in normal mode.
     *
     * @param array $slugs Slug list.
     * @return array Slug list.
     */
    protected function sortSlugs(array $slugs): array
    {
        if (!empty($slugs)) {
            $slugs = array_unique($slugs);

            usort($slugs, function ($left, $right) {
                preg_match('/[1-9]{1}[0-9]*$/', $left, $matchLeft);
                preg_match('/[1-9]{1}[0-9]*$/', $right, $matchRight);

                return current($matchLeft) - current($matchRight);
            });
        }

        return $slugs;
    }
}
