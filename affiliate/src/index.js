import { registerBlockType } from '@wordpress/blocks';

import './style.scss';

import { register } from './insertLink/InsertAffiliateLink';
import OrganicIcon from './OrganicIcon';
import Edit from './productCard/Edit';
import Save from './productCard/Save';

register();

registerBlockType('organic/affiliate-product-card', {
  attributes: {
    productGuid: {
      type: 'string',
      source: 'attribute',
      selector: '[data-organic-affiliate-integration="product-card"]',
      attribute: 'data-organic-affiliate-product-guid',
    },
    displayImage: {
      type: 'boolean',
      default: true,
    },
    displayDescription: {
      type: 'boolean',
      default: true,
    },
    cardRadius: {
      type: 'boolean',
      default: true,
    },
    cardShadow: {
      type: 'boolean',
      default: true,
    },
    textColor: {
      type: 'string',
      default: '#212529',
    },
    linkColor: {
      type: 'string',
      default: '#13A93A',
    },
    backgroundColor: {
      type: 'string',
      default: '#FFFFFF',
    },
  },
  example: {
    attributes: {
      productGuid: '964805c4-6bf7-4418-a112-a58f1565a72d',
      displayImage: true,
      displayDescription: true,
    },
  },
  icon: OrganicIcon,
  edit: Edit,
  save: Save,
});
