<?php
namespace Slug\Model\Behavior;

use Cake\ORM\Behavior;
use Cake\Event\Event;
use Cake\Datasource\EntityInterface;
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
     * Default replacement string
     *
     * @var string
     */
    protected $_defaultReplacement = '-';

    /**
     * Default field to create slug
     *
     * @var string
     */
    protected $_defaultField = 'title';

    /**
     * Default finder method
     *
     * @var string
     */
    protected $_defaultFinder = 'list';

    /**
     * {@inheritdoc}
     */
    public function beforeSave(Event $event, EntityInterface $entity)
    {
        if (empty($this->_config)) {
            $this->_config['slug'] = [];
        }

        foreach ($this->_config as $slug => $config) {
            if (!is_array($config)) {
                $slug = $config;
            }

            if (isset($this->_config[$slug]['present']) && $this->_config[$slug]['present'] === true) {
                continue;
            }

            if (!isset($this->_config[$slug]['field'])) {
                $this->_config[$slug]['field'] = $this->_defaultField;
            }

            if (!$this->_table->hasField($slug)) {
                throw new FieldException(__d('slug', 'Cannot find {0} field in schema.', $slug));
            }

            if (!$this->_table->hasField($this->_config[$slug]['field'])) {
                throw new FieldException(__d('slug', 'Cannot find {0} field as source in schema.', $this->_config[$slug]['field']));
            }

            $schema = $this->_table->getSchema()->getColumn($slug);

            if ($schema['type'] != 'string') {
                throw new FieldTypeException(__d('slug', 'Field {0} should be string type.', $slug));
            }

            if (!isset($this->_config[$slug]['replacement'])) {
                $this->_config[$slug]['replacement'] = $this->_defaultReplacement;
            }

            if (!isset($this->_config[$slug]['length']) || $this->_config[$slug]['length'] > $schema['length']) {
                $this->_config[$slug]['length'] = $schema['length'];
            }

            if (!isset($this->_config[$slug]['finder'])) {
                $this->_config[$slug]['finder'] = $this->_defaultFinder;
            }

            $entity->{$slug} = $this->createSlug($entity->{$this->_config[$slug]['field']}, $slug);
        }
    }

    /**
     * Create unique slug
     *
     * @param string $slug String to slug
     * @param string $field Slug field name
     * @return string Slug
     */
    public function createSlug($slug, $field)
    {
        $config = $this->_config[$field];

        if ((mb_strlen($config['replacement']) + 1) >= $config['length']) {
            throw new LimitException(__d('slug', 'Limit of length in {0} field is too short.', $field));
        }

        if (isset($config['method'])) {
            if (!method_exists($this->_table, $config['method'])) {
                throw new MethodException(__d('slug', 'Method {0} does not exist.', $config['method']));
            }

            $slug = $this->_table->{$config['method']}($slug, $config['replacement']);
        } else {
            $slug = Text::slug(mb_strtolower($slug), [
                'replacement' => $config['replacement']
            ]);
        }

        $slugs = $this->_sortSlugs($this->_getSlugs($slug, $field));

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
            $slugs = $this->_sortSlugs($this->_getSlugs($slug, $field));
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

            if (mb_strlen($string) <= 0) {
                throw new LengthException(__d('slug', 'Cannot create slug because there are no available names.'));
            }

            $slug = $string . $config['replacement'] . $increment;

            // Refresh slugs list
            $slugs = $this->_sortSlugs(array_merge($slugs, $this->_getSlugs($slug, $field)));

            if (in_array($slug, $slugs)) {
                return $this->createSlug($slug, $field);
            }
        }

        return $slug;
    }

    /**
     * Get exists slug list
     *
     * @param string $slug String to slug
     * @param string $field Slug field name
     * @return array List of slugs
     */
    protected function _getSlugs($slug, $field)
    {
        return $this->_table->find($this->_config[$field]['finder'], [
            'valueField' => $field,
        ])->where([
            'OR' => [
                $this->_table->getAlias() . '.' . $field => $slug,
                $this->_table->getAlias() . '.' . $field . ' REGEXP' => '^' . preg_replace('/' . preg_quote($this->_config[$field]['replacement']) . '([1-9]{1}[0-9]*)$/', '', $slug),
            ],
        ])->order([
            $this->_table->getAlias() . '.' . $field => 'ASC',
        ])->toArray();
    }

    /**
     * Sort slug list in normal mode
     *
     * @param array $slugs Slug list
     * @return array Slug list in normal mode
     */
    protected function _sortSlugs($slugs)
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
