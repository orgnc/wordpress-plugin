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
    productCardEditURL: {
      type: 'string',
    },
    productCardSnippet: {
      type: 'string',
    },
  },
  icon: OrganicIcon,
  edit: (props) => (
    <Edit
      {...props}
      productCardCreationURL={organic_affiliate_config_product_card.productCardCreationURL}
    />
  ),
  save: Save,
});
