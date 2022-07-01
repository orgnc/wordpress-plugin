/* eslint-disable camelcase, no-undef, react/jsx-filename-extension, react/jsx-props-no-spreading */
import { registerBlockType } from '@wordpress/blocks';

import './style.scss';

import { register } from './insertLink/InsertAffiliateLink';
import OrganicIcon from './OrganicIcon';
import Edit from './productCard/Edit';
import Save from './productCard/Save';

register(organic_affiliate_config.productSearchPageUrl);

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
      default: false,
    },
  },
  example: {
    attributes: {
      productGuid: '964805c4-6bf7-4418-a112-a58f1565a72d',
      displayImage: true,
      displayDescription: false,
    },
  },
  icon: OrganicIcon,
  edit: (props) => (
    <Edit
      {...props}
      productSearchPageUrl={organic_affiliate_config.productSearchPageUrl}
    />
  ),
  save: Save,
});
