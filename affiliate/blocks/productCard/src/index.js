/* eslint-disable camelcase, no-undef, react/jsx-filename-extension, react/jsx-props-no-spreading */
import { registerBlockType } from '@wordpress/blocks';

import './style.scss';

import OrganicIcon from '../../shared/OrganicIcon';
import { register } from '../insertLink/InsertAffiliateLink';
import Edit from './Edit';
import Save from './Save';

register(organic_affiliate_config_product_card.productSearchPageUrl);

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
    bannerText: {
      type: 'string',
      default: '',
    },
    description: {
      type: 'string',
      default: '',
    },
  },
  example: {
    attributes: {
      productGuid: '964805c4-6bf7-4418-a112-a58f1565a72d',
      displayImage: true,
      displayDescription: false,
      bannerText: 'Best Value',
      description: 'Made from 100% recycled materials',
    },
  },
  icon: OrganicIcon,
  edit: (props) => (
    <Edit
      {...props}
      productSearchPageUrl={organic_affiliate_config_product_card.productSearchPageUrl}
    />
  ),
  save: Save,
});
